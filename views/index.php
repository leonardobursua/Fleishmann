<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
  .filter-input { width: 100%; padding: 3px 6px; font-size: 12px; }
  .sticky-actions { margin: 10px 0; display:flex; gap:8px; flex-wrap:wrap; }
</style>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4>Informes personalizados</h4>

            <div class="row">
              <div class="col-md-3">
                <label>Entidad</label>
                <select id="entity" class="form-control">
                  <?php foreach($entities as $key=>$e){ ?>
                    <option value="<?php echo $key; ?>"><?php echo $e['label']; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-9">
                <label>Campos disponibles</label>
                <div style="margin-bottom:6px;">
                  <button type="button" id="select-all" class="btn btn-default btn-xs">Seleccionar todos</button>
                  <button type="button" id="deselect-all" class="btn btn-warning btn-xs">Deseleccionar</button>
                </div>
                <select id="fields" class="form-control" multiple size="12"></select>
              </div>
            </div>

            <div class="row" style="margin-top:10px;">
              <div class="col-md-3"><label>Desde</label><input type="date" id="date_from" class="form-control"></div>
              <div class="col-md-3"><label>Hasta</label><input type="date" id="date_to" class="form-control"></div>
              <div class="col-md-3"><label>Estado</label><input type="text" id="status" class="form-control" placeholder="Ej: 1 o Activo"></div>
            </div>

            <div class="sticky-actions">
              <button id="btn-generate" class="btn btn-primary">Generar</button>
              <button id="btn-export" class="btn btn-info" title="Exportación backend (sin filtros de columnas)">Exportar (Excel)</button>
              <button id="btn-export-filtered" class="btn btn-success" title="Exporta sólo lo filtrado actualmente en la tabla">Exportar filtrado (Excel)</button>
              <button id="btn-clear-filters" class="btn btn-default">Limpiar filtros</button>
            </div>

            <div id="report-result"></div>
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
  let fieldLabels = {};
  let currentHeaders = {};
  let currentRows = [];

  function fetchFields(){
    $.post(base + '/fields',{entity:$('#entity').val()},function(r){
      try{
        var j=JSON.parse(r);
        var s=$('#fields'); s.empty();
        fieldLabels = j.data.labels || {};
        (j.data.standard||[]).forEach(function(f){ s.append('<option value="'+f+'">'+(fieldLabels[f]||f)+'</option>'); });
        (j.data.custom||[]).forEach(function(cf){ s.append('<option value="'+cf.key+'">'+cf.label+'</option>'); });
      }catch(e){
        console.error('Error parseando /fields:', e, r);
        alert('No se pudieron cargar los campos.');
      }
    }).fail(function(xhr){
      console.error('Error AJAX /fields', xhr.responseText);
      alert('Error cargando campos.');
    });
  }

  function collectFields(){ return $('#fields').val() || []; }

  function buildTable(headers, rows){
    currentHeaders = headers || {};
    currentRows = rows || [];
    var cols = Object.keys(currentHeaders);
    var h = '<div class="table-responsive">';
    h += '<table class="table table-bordered" id="report-table"><thead>';
    h += '<tr>'; cols.forEach(function(k){ h += '<th>'+currentHeaders[k]+'</th>'; }); h += '</tr>';
    h += '<tr class="filters">';
    cols.forEach(function(k){ h += '<th><input type="text" class="filter-input" data-col="'+k+'" placeholder="Filtrar..."/></th>'; });
    h += '</tr>';
    h += '</thead><tbody>';
    if (rows.length){
      rows.forEach(function(r){
        h += '<tr>';
        cols.forEach(function(k){ var v=(r[k]!==undefined && r[k]!==null)? r[k] : ''; h += '<td data-col="'+k+'">'+$('<div>').text(v).html()+'</td>'; });
        h += '</tr>';
      });
    }else{
      h += '<tr><td colspan="'+cols.length+'" class="text-center">Sin resultados</td></tr>';
    }
    h += '</tbody></table></div>';
    return h;
  }

  function applyFilters(){
    const filters = {};
    $('#report-table thead .filter-input').each(function(){
      const col = $(this).data('col');
      const val = $(this).val().toLowerCase();
      filters[col] = val;
    });
    $('#report-table tbody tr').each(function(){
      let show = true;
      $(this).find('td').each(function(){
        const col = $(this).data('col');
        if (filters[col]){
          const cellText = ($(this).text()||'').toLowerCase();
          if (cellText.indexOf(filters[col]) === -1){ show = false; return false; }
        }
      });
      $(this).toggle(show);
    });
  }

  function clearFilters(){
    $('#report-table thead .filter-input').val('');
    $('#report-table tbody tr').show();
  }

  function exportFilteredCSV(){
    const headers = currentHeaders;
    const cols = Object.keys(headers);
    let csv = '';
    csv += '\ufeff';
    csv += cols.map(k => '"'+(headers[k]||k).replace(/"/g,'""')+'"').join(',') + '\n';
    $('#report-table tbody tr:visible').each(function(){
      const tds = $(this).find('td');
      const row = [];
      cols.forEach(function(k, idx){
        const val = $(tds[idx]).text().replace(/\r?\n/g,' ').trim();
        row.push('"'+val.replace(/"/g,'""')+'"');
      });
      csv += row.join(',') + '\n';
    });
    if (!csv || csv === '\ufeff') { alert('No hay filas visibles para exportar.'); return; }
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'informe_filtrado.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  $('#entity').on('change', fetchFields);
  $('#select-all').on('click', function(){ $('#fields option').prop('selected', true); });
  $('#deselect-all').on('click', function(){ $('#fields option').prop('selected', false); });

  $('#btn-generate').on('click', function(){
    var fields = collectFields();
    if(!fields.length){ alert('Debes seleccionar al menos un campo.'); return; }
    $.post(base + '/generate',{
      entity: $('#entity').val(),
      fields: fields,
      date_from: $('#date_from').val(),
      date_to: $('#date_to').val(),
      status: $('#status').val()
    }, function(r){
      try{
        var j=JSON.parse(r);
        if(!j.success){ alert(j.error||'No se pudo generar el informe'); return; }
        $('#report-result').html(buildTable(j.headers||{}, j.rows||[]));
        $('#report-table thead .filter-input').on('keyup change', applyFilters);
      }catch(e){
        console.error('Error parseando /generate:', e, r);
        alert('Error generando el informe.');
      }
    }).fail(function(xhr){
      console.error('Error AJAX /generate', xhr.responseText);
      alert('Error generando el informe.');
    });
  });

  $('#btn-export').on('click', function(){
    var fields = collectFields();
    if(!fields.length){ alert('Debes seleccionar al menos un campo.'); return; }
    var url = base + '/export_excel?entity='+encodeURIComponent($('#entity').val())
      +'&fields='+encodeURIComponent(fields.join(','))
      +'&date_from='+encodeURIComponent($('#date_from').val())
      +'&date_to='+encodeURIComponent($('#date_to').val())
      +'&status='+encodeURIComponent($('#status').val());
    window.open(url,'_blank');
  });

  $('#btn-export-filtered').on('click', exportFilteredCSV);
  $('#btn-clear-filters').on('click', clearFilters);

  fetchFields();
})();
</script>
