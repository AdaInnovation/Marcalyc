<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
<style>
.label-success {
    background-color: #5cb85c;
}
.label-danger {
    background-color: #d9534f;
}
.label-warning {
    background-color: #f0ad4e;
}
.label {
    display: inline;
    padding: .2em .6em .3em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .25em;
}
.label {
    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
}

.titulo{
    margin-bottom: 0px;
    margin-right: 21px;
    margin-left: 20px;
    color: #696464;
    margin-top: auto;
}
</style>

<div style="margin-top: -20px;">
    <p style="color:red; font-size:25px">
        {$Errores}
    </p>
    <form id="index" class="pkp_form" action="{plugin_url path="importIssues"}" method="post">
        {$Resultados}
        <div style="text-align: center;margin-top: 5%;">
            {fbvFormArea id="submissionsXmlForm"}
                {fbvFormSection}
                    <ul class="export_actions">
                        {if !empty($cancelar)}
                            <li class="export_action">
                                <a href="{$cancelar}">
                                    <button class="btn btn-danger" type="button" id="cancelar"><i class="fa fa-trash"></i> Cancelar</button>					
                                </a>
                            </li>
                        {/if}
                        <li class="export_action">
                            <button class="btn btn-success" type="submit" id="guardar" ><i class="fa fa-save"></i> Guardar</button>							
                        </li>
                        <span class="pkp_spinner is_visible" id="charging" style="display: none;"></span>
                    </ul>
                {/fbvFormSection}
            {/fbvFormArea}
        </div>
        <input type="hidden" name="temporaryFileId" id="temporaryFileId" value="{$temporaryFileId}" />
        
    </form>
<div>
<script>

   $('#guardar').click(function() {
        $('#charging').show();
        $('#guardar').submit();
        $('#cancelar').attr("disabled", true);
        //$('#guardar').attr("disabled", true);
    });

</script>
