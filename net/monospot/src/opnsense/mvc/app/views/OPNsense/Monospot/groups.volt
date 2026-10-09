{#
 # Monospot > Gruplar: MAC bazlı grup listesi (ekle/düzenle/sil).
 #}
<script>
    'use strict';

    $(document).ready(function () {
        $("#{{formGridGroup['table_id']}}").UIBootgrid({
            search: '/api/monospot/groups/search/',
            get: '/api/monospot/groups/get/',
            add: '/api/monospot/groups/add/',
            set: '/api/monospot/groups/set/',
            del: '/api/monospot/groups/del/'
        });
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridGroup)}}
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditGroup,'id':formGridGroup['edit_dialog_id'],'label':lang._('Grup')]) }}
