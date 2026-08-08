define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {
    return {
        init: function(contextid) {
            var botao;

            if (document.getElementById("page-contentbank")) {

                botao = `
                    <a href="${M.cfg.wwwroot}/local/aacura_core/h5p/index.php?contextid=${contextid}" 
                       class="d-flex align-items-center btn btn-dark text-nowrap mr-1">
                        ${M.util.get_string("h5p-manager", "local_aacura_core")}
                    </a>`;
                $("#page-contentbank .content-bank-container .cb-toolbar-container").prepend(botao);
            }

            if (document.getElementById("page-mod-h5pactivity-mod")) {
                botao = `
                    <div>
                        <a href="${M.cfg.wwwroot}/local/aacura_core/h5p/index.php?contextid=${contextid}" target="_blank"
                           class="d-flex align-items-center btn btn-dark text-nowrap">
                            ${M.util.get_string("h5p-manager", "local_aacura_core")}
                        </a>
                    </div>`;
                $("#id_error_contentbank").before(botao);
            }

            if (document.getElementById("page-mod-scorm-mod")) {
                botao = `
                    <div>
                        <a href="${M.cfg.wwwroot}/local/aacura_core/h5p/index.php?contextid=${contextid}&scorm=true" target="_blank"
                           class="d-flex align-items-center btn btn-dark text-nowrap">
                            ${M.util.get_string("h5p-manager-scorm", "local_aacura_core")}
                        </a>
                    </div>`;
                $("#id_packagefile_fieldset").before(botao);
            }
        }
    };
});
