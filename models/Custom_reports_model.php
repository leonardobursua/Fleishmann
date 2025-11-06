<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Custom_reports_model extends App_Model
{
    private $similarity_threshold = 70; // % mínimo para fuzzy

    public function get_entities()
    {
        $out = [
            'customers' => ['label'=>'Clientes','table'=>db_prefix().'clients','pk'=>'userid','date_field'=>'datecreated','fieldto'=>'customers'],
            'leads'     => ['label'=>'Leads','table'=>db_prefix().'leads','pk'=>'id','date_field'=>'dateadded','fieldto'=>'leads'],
            'projects'  => ['label'=>'Proyectos','table'=>db_prefix().'projects','pk'=>'id','date_field'=>'start_date','fieldto'=>'projects'],
            'proposals' => ['label'=>'Propuestas','table'=>db_prefix().'proposals','pk'=>'id','date_field'=>'date','fieldto'=>'proposals'],
            'contracts' => ['label'=>'Contratos','table'=>db_prefix().'contracts','pk'=>'id','date_field'=>'datestart','fieldto'=>'contracts'],
            'tasks'     => ['label'=>'Tareas','table'=>db_prefix().'tasks','pk'=>'id','date_field'=>'dateadded','fieldto'=>'tasks'],
            'estimates' => ['label'=>'Presupuestos','table'=>db_prefix().'estimates','pk'=>'id','date_field'=>'date','fieldto'=>'estimates'],
        ];
        if ($this->db->table_exists(db_prefix().'tickets')) {
            $out['tickets'] = ['label'=>'Tickets','table'=>db_prefix().'tickets','pk'=>'ticketid','date_field'=>'date','fieldto'=>'tickets'];
        }
        return $out;
    }

    public function get_role_id_by_staff($staffid){
        $this->db->select('role'); $this->db->where('staffid',(int)$staffid);
        $r = $this->db->get(db_prefix().'staff')->row_array();
        return $r ? (int)$r['role'] : null;
    }
    public function get_user_permissions($staffid, $entity){
        $role_id = $this->get_role_id_by_staff($staffid);
        if (!$role_id) return ['own'=>0,'global'=>0,'visualize'=>0];
        $this->db->where(['role_id'=>$role_id,'entity'=>$entity]);
        $p = $this->db->get(db_prefix().'customreport_permissions')->row_array();
        if (!$p) return ['own'=>0,'global'=>0,'visualize'=>0];
        return ['own'=>(int)$p['can_view_own'], 'global'=>(int)$p['can_view_global'], 'visualize'=>(int)$p['can_visualize']];
    }
    public function get_roles(){ return $this->db->get(db_prefix().'roles')->result_array(); }
    public function get_permissions_matrix(){
        $rows=$this->db->get(db_prefix().'customreport_permissions')->result_array(); $m=[];
        foreach($rows as $r){ $m[$r['role_id']][$r['entity']] = ['own'=>(int)$r['can_view_own'],'global'=>(int)$r['can_view_global'],'visualize'=>(int)$r['can_visualize']]; }
        return $m;
    }
    public function save_permissions($data){
        if (!is_array($data)) return false;
        foreach($data as $role_id=>$entities){
            foreach($entities as $entity=>$perms){
                $row = [
                    'role_id'=>(int)$role_id,'entity'=>$entity,
                    'can_view_own'=>!empty($perms['own'])?1:0,
                    'can_view_global'=>!empty($perms['global'])?1:0,
                    'can_visualize'=>!empty($perms['visualize'])?1:0
                ];
                $exists=$this->db->get_where(db_prefix().'customreport_permissions',['role_id'=>$role_id,'entity'=>$entity])->row_array();
                if($exists){ $this->db->where('id',$exists['id']); $this->db->update(db_prefix().'customreport_permissions',$row); }
                else { $this->db->insert(db_prefix().'customreport_permissions',$row); }
            }
        }
        return true;
    }

    private function labels_common(){ return [
        'id'=>'ID','userid'=>'Cliente','company'=>'Nombre de la empresa','phonenumber'=>'Teléfono','email'=>'Correo electrónico',
        'city'=>'Ciudad','state'=>'Departamento','country'=>'País','address'=>'Dirección','zip'=>'Código Postal',
        'datecreated'=>'Fecha de creación','dateadded'=>'Fecha de creación','datestart'=>'Fecha inicio','date'=>'Fecha',
        'duedate'=>'Fecha de vencimiento','status'=>'Estado','subject'=>'Asunto','assigned'=>'Asignado a',
        'total'=>'Total','name'=>'Nombre','description'=>'Descripción','currency'=>'Moneda','client'=>'Cliente',
        'customerid'=>'Cliente','clientid'=>'Cliente','vat'=>'NIT','number'=>'Número','priority'=>'Prioridad',
        'startdate'=>'Fecha inicio','datefinished'=>'Fecha finalización','active'=>'Activo'
    ];}
    private function labels_by_entity($entity){
        $map=$this->labels_common();
        if($entity=='customers'){
            $map=array_merge($map,[
                'billing_street'=>'Dirección de facturación','billing_city'=>'Ciudad de facturación','billing_state'=>'Departamento de facturación',
                'billing_zip'=>'C.P. de facturación','billing_country'=>'País de facturación',
                'shipping_street'=>'Dirección de envío','shipping_city'=>'Ciudad de envío','shipping_state'=>'Departamento de envío',
                'shipping_zip'=>'C.P. de envío','shipping_country'=>'País de envío',
            ]);
        } elseif ($entity=='projects'){
            $map=array_merge($map,[
                'clientid'=>'Cliente','start_date'=>'Fecha inicio','deadline'=>'Fecha límite',
                'progress'=>'Progreso (%)','project_cost'=>'Costo del proyecto',
                'project_rate_per_hour'=>'Tarifa por hora','estimated_hours'=>'Horas estimadas','addedfrom'=>'Creado por'
            ]);
        }
        return $map;
    }
    private function project_status_name($v){ $m=[1=>'No iniciado',2=>'En progreso',3=>'En espera',4=>'Cancelado',5=>'Finalizado']; return isset($m[$v])?$m[$v]:$v; }

    public function get_fields_for_entity($entity){
        $entities = $this->get_entities();
        if (!isset($entities[$entity])) return ['standard'=>[], 'custom'=>[], 'labels'=>[]];
        $table = $entities[$entity]['table'];
        $standard = $this->db->list_fields($table);
        $this->db->where('fieldto', $entities[$entity]['fieldto']); $this->db->where('active', 1);
        $cfs = $this->db->get(db_prefix().'customfields')->result_array();
        $custom=[]; foreach($cfs as $cf){ $custom[]=['key'=>'cf_'.$cf['id'],'label'=>$cf['name'],'id'=>(int)$cf['id']]; }
        $labels=[]; $map=$this->labels_by_entity($entity);
        foreach($standard as $f){ $labels[$f]=isset($map[$f])?$map[$f]:ucfirst(str_replace('_',' ',$f)); }
        foreach($custom as $cf){ $labels[$cf['key']]=$cf['label']; }
        $labels['client_name']='Cliente'; $labels['assigned_name']='Asignado a'; $labels['status_name']='Estado';
        return ['standard'=>$standard,'custom'=>$custom,'labels'=>$labels];
    }

    public function get_all_fields_map(){
        $entities=$this->get_entities(); $out=[];
        foreach($entities as $k=>$e){ $out[$k]=$this->get_fields_for_entity($k); }
        return $out;
    }

    public function get_field_visibility_for($entity){
        $rows=$this->db->get_where(db_prefix().'customreport_field_permissions',['entity'=>$entity])->result_array();
        $mapVisible=[]; $mapLabel=[];
        foreach($rows as $r){
            $mapVisible[$r['field_key']] = (int)$r['visible'];
            if (!empty($r['label_custom'])) { $mapLabel[$r['field_key']] = $r['label_custom']; }
        }
        return ['visible'=>$mapVisible, 'labels'=>$mapLabel];
    }
    public function get_field_visibility_matrix(){
        $rows=$this->db->get(db_prefix().'customreport_field_permissions')->result_array();
        $m=[];
        foreach($rows as $r){
            $e = $r['entity']; $f = $r['field_key'];
            if (!isset($m[$e])) $m[$e]=[];
            if (!isset($m[$e]['visible'])) $m[$e]['visible']=[];
            if (!isset($m[$e]['label_custom'])) $m[$e]['label_custom']=[];
            $m[$e]['visible'][$f]=(int)$r['visible'];
            if (!empty($r['label_custom'])) { $m[$e]['label_custom'][$f] = $r['label_custom']; }
        }
        return $m;
    }
    public function save_field_visibility($data){
        if (!is_array($data)) return false;
        foreach($data as $entity=>$fields){
            foreach($fields as $field_key=>$payload){
                $visible = is_array($payload) ? (!empty($payload['visible'])?1:0) : (!empty($payload)?1:0);
                $label_custom = is_array($payload) && isset($payload['label_custom']) ? trim($payload['label_custom']) : null;

                $row=['entity'=>$entity,'field_key'=>$field_key,'visible'=>$visible,'label_custom'=>$label_custom];
                $exists=$this->db->get_where(db_prefix().'customreport_field_permissions',['entity'=>$entity,'field_key'=>$field_key])->row_array();
                if($exists){ $this->db->where('id',$exists['id']); $this->db->update(db_prefix().'customreport_field_permissions',$row); }
                else { $this->db->insert(db_prefix().'customreport_field_permissions',$row); }
            }
        }
        return true;
    }

    public function get_fields_for_entity_filtered($entity){
        $base = $this->get_fields_for_entity($entity);
        $vf = $this->get_field_visibility_for($entity);
        $visibility = $vf['visible']; $labels_override = $vf['labels'];

        if (!empty($visibility)) {
            $base['standard'] = array_values(array_filter($base['standard'], function($f) use ($visibility){ return !array_key_exists($f, $visibility) || (int)$visibility[$f] === 1; }));
            $base['custom']   = array_values(array_filter($base['custom'], function($cf) use ($visibility){ return !array_key_exists($cf['key'], $visibility) || (int)$visibility[$cf['key']] === 1; }));
            $base['labels']   = array_filter($base['labels'], function($label_key) use ($visibility){ return !array_key_exists($label_key, $visibility) || (int)$visibility[$label_key] === 1; }, ARRAY_FILTER_USE_KEY);
        }
        foreach($labels_override as $k=>$v){ $base['labels'][$k] = $v; }
        return $base;
    }

    private function normalize_txt($s){
        $s = @iconv('UTF-8','ASCII//TRANSLIT',$s);
        $s = strtolower($s);
        return $s;
    }

    public function get_preview($entity, $fields, $date_from=null, $date_to=null, $status=null, $group_by=null, $show_totals=false, $group_as_filter=false, $perm=null)
    {
        $e=$this->get_entities(); if(!isset($e[$entity])) return ['rows'=>[], 'headers'=>[]];
        $table=$e[$entity]['table']; $pk=$e[$entity]['pk']; $date_field=$e[$entity]['date_field']; $columns=$this->db->list_fields($table);
        if(!is_array($fields)) $fields=[]; if(empty($fields)) return ['rows'=>[], 'headers'=>[]];

        $selects=[]; $joins=[]; $wheres=[]; $added_cf=[]; $need_client_join=false; $need_assigned_join=false;

        foreach($fields as $f){ if(in_array($f,['client','customerid','clientid'])) $need_client_join=true; if($f==='assigned') $need_assigned_join=true; }
        if(in_array($group_by,['client','customerid','clientid'])) $need_client_join=true;

        foreach($fields as $f){
            if(strpos($f,'cf_')===0){
                $cfid=(int)str_replace('cf_','',$f);
                if(!isset($added_cf[$cfid])){ $alias='cf_'.$cfid; $joins[]="LEFT JOIN ".db_prefix()."customfieldsvalues `$alias` ON `$alias`.relid=`$table`.`$pk` AND `$alias`.fieldid=".$cfid; $added_cf[$cfid]=true; }
                $selects[]="`cf_$cfid`.value AS `cf_$cfid`";
            } elseif(in_array($f,$columns)){
                $selects[]="`$table`.`$f` AS `$f`";
            }
        }
        if(empty($selects)) $selects[]="`$table`.`$pk` AS `$pk`";

        if($need_client_join || in_array($entity,['projects','estimates','tickets'])){
            $cid_col = null;
            foreach (['clientid','customerid','userid','contactid','rel_id'] as $col) {
                if (in_array($col, $columns)) { $cid_col = $col; break; }
            }
            if ($cid_col) {
                $joins[]="LEFT JOIN ".db_prefix()."clients c ON c.userid=`$table`.`$cid_col`";
                $selects[]="c.company AS client_name";
            }
        }

        if($need_assigned_join || in_array($entity,['leads','tasks','projects','tickets'])){
            if (in_array('assigned',$columns)) {
                $joins[]="LEFT JOIN ".db_prefix()."staff s ON s.staffid=`$table`.`assigned`";
                $selects[]="CONCAT(s.firstname,' ',s.lastname) AS assigned_name";
            }
        }

        if(!empty($date_from) && !empty($date_field) && in_array($date_field,$columns)) $wheres[]="`$table`.`$date_field` >= ".$this->db->escape($date_from);
        if(!empty($date_to) && !empty($date_field) && in_array($date_field,$columns))   $wheres[]="`$table`.`$date_field` <= ".$this->db->escape($date_to);
        if($status && in_array('status',$columns))    $wheres[]="`$table`.`status` = ".$this->db->escape($status);

        if($perm && empty($perm['global'])){
            $staffid = get_staff_user_id();
            if ($entity === 'projects') {
                $conds = [];
                $proj_ids = $this->db->select('project_id')->where('staff_id', $staffid)->get(db_prefix().'project_members')->result_array();
                $ids = array_filter(array_map('intval', array_column($proj_ids, 'project_id')));
                if (!empty($ids)) { $conds[] = "`$table`.`id` IN (" . implode(',', $ids) . ")"; }
                if (in_array('addedfrom', $columns)) { $conds[] = "`$table`.`addedfrom` = " . $this->db->escape($staffid); }
                if (!empty($conds)) { $wheres[] = '('.implode(' OR ', $conds).')'; } else { $wheres[]='1=0'; }
            }
            if ($entity === 'customers') {
                $proj = $this->db->select('DISTINCT p.clientid')
                                 ->from(db_prefix().'project_members pm')
                                 ->join(db_prefix().'projects p', 'p.id = pm.project_id', 'inner')
                                 ->where('pm.staff_id', $staffid)
                                 ->where('p.clientid IS NOT NULL', null, false)
                                 ->get()->result_array();
                $client_ids = array_filter(array_map('intval', array_column($proj, 'clientid')));
                if (!empty($client_ids)) {
                    $wheres[] = "`$table`.`userid` IN (" . implode(',', $client_ids) . ")";
                } else {
                    $wheres[] = "1=0";
                }
            }
        }

        $sql="SELECT ".implode(',', array_unique($selects))." FROM `$table` ";
        if(!empty($joins)) $sql .= implode(' ', $joins).' ';
        if(!empty($wheres)) $sql .= 'WHERE '.implode(' AND ', $wheres).' ';
        $sql .= "LIMIT 700";

        try {
            $rows=$this->db->query($sql)->result_array();
        } catch (Throwable $ex) {
            log_message('error', 'CustomReports SQL Error: '.$ex->getMessage().' | SQL: '.$sql);
            return ['rows'=>[], 'headers'=>[], 'error'=>'Error al ejecutar la consulta. Revisa los campos seleccionados o permisos.'];
        }

        if($entity==='projects'){ foreach($rows as &$r){ if(isset($r['status'])) $r['status']=$this->project_status_name((int)$r['status']); } unset($r); }
        if($entity==='customers'){ foreach($rows as &$r){ if(!isset($r['client_name']) && isset($r['company'])) $r['client_name']=$r['company']; } unset($r); }

        if($perm && empty($perm['global'])){
            $staffid = get_staff_user_id();
            $staff = $this->db->select('firstname, lastname, email')
                              ->where('staffid', $staffid)
                              ->get(db_prefix().'staff')->row_array();
            $full_name = strtolower(trim(($staff['firstname'] ?? '').' '.($staff['lastname'] ?? '')));
            $email = strtolower($staff['email'] ?? '');

            $filtered = [];
            foreach($rows as $r){
                $keep = false;
                foreach(['description','notes','details','subject','name','content','message'] as $col){
                    if(isset($r[$col]) && $r[$col]){
                        $text = strtolower($r[$col]);
                        similar_text($full_name, $text, $p1); similar_text($email, $text, $p2);
                        if ($p1 >= $this->similarity_threshold || $p2 >= $this->similarity_threshold) { $keep = true; break; }
                    }
                }
                if (!$keep){
                    foreach(['assigned','addedfrom','staffid','employee','responsible','created_by','updated_by','owner','ownerid'] as $col){
                        if(isset($r[$col]) && (int)$r[$col] === (int)$staffid){ $keep = true; break; }
                    }
                }
                if($keep) $filtered[] = $r;
            }
            $rows = $filtered;
        }

        $meta=$this->get_fields_for_entity($entity);
        $labels=$meta['labels'];
        $vf = $this->get_field_visibility_for($entity);
        foreach($vf['labels'] as $k=>$v){ $labels[$k]=$v; }

        $headers=[];
        foreach($fields as $f){
            if(in_array($f,['client','customerid','clientid'])) $headers['client_name']= isset($labels['client_name'])?$labels['client_name']:'Cliente';
            elseif($f==='assigned') $headers['assigned_name']= isset($labels['assigned_name'])?$labels['assigned_name']:'Asignado a';
            else $headers[$f]=isset($labels[$f])?$labels[$f]:$f;
        }
        if(($need_client_join || in_array($entity,['projects','customers','tickets'])) && !isset($headers['client_name'])) $headers['client_name']= isset($labels['client_name'])?$labels['client_name']:'Cliente';

        return ['rows'=>$rows,'headers'=>$headers];
    }
}

    
    public function restrict_projects_to_user($staffid)
    {
        $this->db->join(db_prefix() . 'project_members AS pm', 'pm.project_id = projects.id', 'left');
        $this->db->group_start();
        $this->db->where('pm.staff_id', $staffid);
        $this->db->or_where('projects.addedfrom', $staffid);
        $this->db->group_end();
    }

}