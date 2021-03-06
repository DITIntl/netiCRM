  <div id="dialog-confirm" title="{ts}Procceed Receipt Generation?{/ts}" style="display:none;">
    <p>{ts}This will take a period of time{/ts}<br />{ts}Are you sure you want to continue?{/ts}</p>
  </div>
<div class="form-item">
  <div class="crm-section">
    <div class="label"><label>{$form.year.label}</label></div>
    <div class="content">{$form.year.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label"><label>{$form.contribution_type_id.label}</label></div>
    <div class="content">{$form.contribution_type_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label"><label>{$form.is_recur.label}</label></div>
    <div class="content">{$form.is_recur.html}</div>
    <div class="clear"></div>
  </div>
</div>
<div class="spacer"></div>
<div class="form-item">
 {$form.buttons.html}
</div>
{literal}
<script type="text/javascript" >
cj(document).ready(function(){
  cj( "#dialog-confirm" ).dialog({
    autoOpen: false,
    resizable: false,
    width:450,
    height:250,
    modal: true,
    buttons: {
      "Go!": function() {
        cj( this ).dialog( "close" );
        document.AnnualReceipt.submit();
      },
      Cancel: function() {
        cj( this ).dialog( "close" );
        return false;
      }
    }
  });
  cj("#AnnualReceipt").submit(function(){
    var result = cj('#dialog-confirm').dialog('open');
    return false;
  });
});
</script>
{/literal}
