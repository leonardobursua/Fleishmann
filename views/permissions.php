<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4>Permisos de informes</h4>
            <p>Configura por <b>rol</b> el acceso por entidad: Ver propios, Ver global y Visualizar/Exportar.</p>
            <div class="table-responsive">
              <table class="table table-bordered" id="perm-table">
                <thead>
                  <tr>
                    <th>Rol \ Entidad</th>
                    <?php foreach($entities as $key=>$e){ ?>
                      <th><?php echo $e['label']; ?></th>
                    <?php } ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($roles as $role){ ?>
                    <tr data-role="<?php echo $role['roleid']; ?>">
                      <td><b><?php echo $role['name']; ?></b></td>
                      <?php foreach($entities as $ekey=>$e){
                        $p = $matrix[$role['roleid']][$ekey] ?? ['own'=>0,'global'=>0,'visualize'=>0]; ?>
                        <td>
                          <label style="display:block;"><input type="checkbox" class="p-own" <?php echo $p['own']?'checked':''; ?>> Ver propios</label>
                          <label style="display:block;"><input type="checkbox" class="p-global" <?php echo $p['global']?'checked':''; ?>> Ver global</label>
                          <label style="display:block;"><input type="checkbox" class="p-visualize" <?php echo $p['visualize']?'checked':''; ?>> Visualizar/Exportar</label>
                        </td>
                      <?php } ?>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <button id="save" class="btn btn-success">Guardar permisos</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
(function(){
  const base = "<?php echo admin_url('custom_reports'); ?>";
  const entities = <?php echo json_encode(array_keys($entities)); ?>;
  $('#save').on('click', function(){
    const payload = {};
    $('#perm-table tbody tr').each(function(){
      const role = $(this).data('role'); payload[role] = payload[role] || {};
      $(this).find('td:gt(0)').each(function(i){
        const entity = entities[i];
        payload[role][entity] = {
          own: $(this).find('.p-own').is(':checked') ? 1 : 0,
          global: $(this).find('.p-global').is(':checked') ? 1 : 0,
          visualize: $(this).find('.p-visualize').is(':checked') ? 1 : 0
        };
      });
    });
    $.post(base + '/save_permissions',{payload: JSON.stringify(payload)}, function(r){
      try{ var j=JSON.parse(r); alert(j.success ? 'Guardado' : 'No se pudo guardar'); }catch(e){ alert('Error interno'); }
    });
  });
})();
</script>
