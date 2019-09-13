<div style="margin-top: -20px;">
    {$Resultados}
    <p style="color:red; font-size:25px">
        {$Errores}
    </p>
<div>

<!--
<div style="text-align: center;margin-top: 5%;">
    <form id="index" class="pkp_form" action="{plugin_url path="index"}" method="post">
        {fbvFormButtons submitText="plugins.importexport.marcalycImporter.again" hideCancel="true"}
    </form>
</div>
-->

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