{strip}
{assign var="pageTitle" value="plugins.importexport.marcalycImporter.displayName"}
{include file="common/header.tpl"}
{/strip}
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
<script src="https://kit.fontawesome.com/bc351813b5.js" crossorigin="anonymous"></script>
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
<div class="col-12 col-lg-12 col-md-12" style="padding: 20px;">
    {$Resultados}
    <div style="text-align: center;margin-top: 5%;">
        <form id="index" class="pkp_form" action="{plugin_url path="index"}" method="post">
            {fbvFormArea id="submissionsXmlForm"}
                {fbvFormSection}
                    <ul class="export_actions">
                        <li class="export_action">								
                            {fbvElement type="submit" label="plugins.importexport.marcalycImporter.again" id="again" inline=true}
                        </li>
                        {if !empty($numeros_url)}
                            <li class="export_action">
                                <a href="{$numeros_url}">							
                                    {fbvElement type="button" label="plugins.importexport.marcalycImporter.ver_numeros" id="ver_numeros" translate=true inline=true}
                                </a>
                            </li>
                        {/if}
                    </ul>
                {/fbvFormSection}
            {/fbvFormArea}
        </form>
    </div>
<div>

{include file="common/footer.tpl"}