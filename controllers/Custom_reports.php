<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Custom_reports extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('custom_reports_model');
        $this->load->helper('custom_reports');
    }

    public function index()
    {
        $data['entities'] = $this->custom_reports_model->get_entities();
        $data['title']    = 'Informes personalizados';
        $this->load->view('custom_reports/index', $data);
    }

    private function is_admin_user($staffid)
    {
        if (function_exists('is_admin') && is_admin()) { return true; }
        $this->db->where(['staffid' => $staffid, 'admin' => 1]);
        return $this->db->count_all_results(db_prefix() . 'staff') > 0;
    }

    public function fields()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        $entity = $this->input->post('entity', true);
        $fields = $this->custom_reports_model->get_fields_for_entity_filtered($entity);
        echo json_encode(['success'=>true,'data'=>$fields]);
    }

    public function generate()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        $entity = $this->input->post('entity');
        $fields = $this->input->post('fields');
        $date_from = $this->input->post('date_from');
        $date_to = $this->input->post('date_to');
        $status = $this->input->post('status');
        $group_by = $this->input->post('group_by');
        $show_totals = $this->input->post('show_totals') ? true : false;
        $group_as_filter = $this->input->post('group_as_filter') ? true : false;

        $staffid = get_staff_user_id();
        $is_admin_user = $this->is_admin_user($staffid);

        $has_global_perm = ( ($is_admin_user) || has_permission($entity, '', 'view') );
        $has_own_perm    = has_permission($entity, '', 'view_own');

        if ($is_admin_user) {
            $perm = ['own'=>1,'global'=>1,'visualize'=>1];
        } else {
            $perm = $this->custom_reports_model->get_user_permissions($staffid, $entity);
            if (!$has_global_perm && !$has_own_perm && !$perm['own'] && !$perm['global']) {
                echo json_encode(['success'=>false,'error'=>'Sin permisos para esta entidad']); return;
            }
        }

        if (!is_array($fields) || count($fields) === 0) {
            echo json_encode(['success'=>false,'error'=>'Debes seleccionar al menos un campo.']); return;
        }

        $result = $this->custom_reports_model->get_preview($entity, $fields, $date_from, $date_to, $status, $group_by, $show_totals, $group_as_filter, [
            'own'     => ($is_admin_user || $has_own_perm || (!empty($perm['own']))) ? 1 : 0,
            'global'  => ($is_admin_user || $has_global_perm || (!empty($perm['global']))) ? 1 : 0,
            'visualize' => 1,
        ]);
        echo json_encode(['success'=>true] + $result);
    }

    public function export_excel()
    {
        $entity = $this->input->get('entity');
        $fields = $this->input->get('fields') ? explode(',', $this->input->get('fields')) : [];
        $date_from = $this->input->get('date_from');
        $date_to = $this->input->get('date_to');
        $status = $this->input->get('status');
        $group_by = $this->input->get('group_by');
        $show_totals = $this->input->get('show_totals') ? true : false;
        $group_as_filter = $this->input->get('group_as_filter') ? true : false;

        $staffid = get_staff_user_id();
        $is_admin_user = $this->is_admin_user($staffid);
        $has_global_perm = ( ($is_admin_user) || has_permission($entity, '', 'view') );
        $has_own_perm    = has_permission($entity, '', 'view_own');

        if (!($is_admin_user || $has_global_perm || $has_own_perm)) {
            show_error('No tienes permiso para exportar/visualizar');
        }

        $result = $this->custom_reports_model->get_preview($entity, $fields, $date_from, $date_to, $status, $group_by, $show_totals, $group_as_filter, [
            'own'     => ($is_admin_user || $has_own_perm) ? 1 : 0,
            'global'  => ($is_admin_user || $has_global_perm) ? 1 : 0,
            'visualize' => 1,
        ]);
        $rows = isset($result['rows']) ? $result['rows'] : [];
        $headers = isset($result['headers']) ? $result['headers'] : [];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="informe.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Reporte generado el', date('Y-m-d H:i:s')]);

        if (!empty($rows) && !empty($headers)) {
            fputcsv($out, array_values($headers));
            foreach ($rows as $r) {
                $line = [];
                foreach (array_keys($headers) as $k) { $line[] = isset($r[$k]) ? $r[$k] : ''; }
                fputcsv($out, $line);
            }
        } else {
            fputcsv($out, ['Sin resultados']);
        }
        fclose($out);
        exit;
    }

    public function permissions()
    {
        if (!is_admin()) { show_404(); }
        $data['entities'] = $this->custom_reports_model->get_entities();
        $data['roles']    = $this->custom_reports_model->get_roles();
        $data['matrix']   = $this->custom_reports_model->get_permissions_matrix();
        $data['title']    = 'Permisos de informes';
        $this->load->view('custom_reports/permissions', $data);
    }

    public function save_permissions()
    {
        if (!is_admin() || !$this->input->is_ajax_request()) { show_404(); }
        $payload = $this->input->post('payload');
        $ok = $this->custom_reports_model->save_permissions(json_decode($payload, true));
        echo json_encode(['success'=>$ok]);
    }

    public function fields_matrix()
    {
        if (!is_admin()) { show_404(); }
        $data['entities'] = $this->custom_reports_model->get_entities();
        $data['fields_map'] = $this->custom_reports_model->get_all_fields_map();
        $data['matrix'] = $this->custom_reports_model->get_field_visibility_matrix();
        $data['title'] = 'Campos habilitados';
        $this->load->view('custom_reports/fields_matrix', $data);
    }

    public function save_fields_visibility()
    {
        if (!is_admin() || !$this->input->is_ajax_request()) { show_404(); }
        $payload = $this->input->post('payload');
        $ok = $this->custom_reports_model->save_field_visibility(json_decode($payload, true));
        echo json_encode(['success'=>$ok]);
    }
}
