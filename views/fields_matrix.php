<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>.label-input{width:100%;max-width:420px}</style>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4>Campos habilitados</h4>
            <p>Activa/Desactiva y <b>renombra</b> las etiquetas visibles que verán los usuarios en el generador y exportaciones.</p>
            <?php foreach($entities as $ekey=>$e){
              $labels = $fields_map[$ekey]['labels'] ?? [];
              $m = $matrix[$ekey] ?? ['visible'=>[], 'label_custom'=>[]];
              $vis = $m['visible'] ?? [];
              $lab = $m['label_custom'] ?? [];
            ?>
            <h5 style="margin-top:20px;"><?php echo $e['label']; ?></h5>
            <div class="table-responsive">
              <table class="table table-bordered field-matrix" data-entity="<?php echo $ekey; ?>">
                <thead>
                  <tr>
                    <th>Campo</th>
                    <th>Etiqueta visible (editable)</th>
                    <th>Visible</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($labels as $field_key=>$label){
                    $visible = isset($vis[$field_key]) ? (int)$vis[$field_key] : 1;
                    $label_custom = isset($lab[$field_key]) ? $lab[$field_key] : $label; ?>
                    <tr>
                      <td><small class="text-muted"><?php echo $field_key; ?></small></td>
                      <td><input type="text" class="form-control label-input f-label" data-field="<?php echo $field_key; ?>" value="<?php echo htmlspecialchars($label_custom); ?>"></td>
                      <td class="text-center"><input type="checkbox" class="f-visible" <?php echo $visible ? 'checked' : ''; ?> data-field="<?php echo $field_key; ?>"></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <?php } ?>
            <button id="save-fields" class="btn btn-primary">Guardar cambios</button>
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
  $('#save-fields').on('click', function(){
    const payload = {};
    $('.field-matrix').each(function(){
      const entity = $(this).data('entity');
      payload[entity] = payload[entity] || {};
      $(this).find('tbody tr').each(function(){
        const field = $(this).find('.f-visible').data('field');
        const visible = $(this).find('.f-visible').is(':checked') ? 1 : 0;
        const label_custom = $(this).find('.f-label').val();
        payload[entity][field] = { visible: visible, label_custom: label_custom };
      });
    });
    $.post(base + '/save_fields_visibility', {payload: JSON.stringify(payload)}, function(r){
      try{ var j=JSON.parse(r); alert(j.success ? 'Guardado' : 'No se pudo guardar'); }catch(e){ alert('Error interno'); }
    });
  });
})();
</script>
