{#
 # Monospot > Kullanıcılar: hotspot SQLite veritabanı üzerinde liste/ekle/düzenle/sil.
 #}
<script>
    'use strict';

    $(document).ready(function () {
        $("#{{formGridUser['table_id']}}").UIBootgrid({
            search: '/api/monospot/users/search/',
            get: '/api/monospot/users/get/',
            add: '/api/monospot/users/add/',
            set: '/api/monospot/users/set/',
            del: '/api/monospot/users/del/'
        });
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridUser)}}
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditUser,'id':formGridUser['edit_dialog_id'],'label':lang._('Kullanıcı')]) }}
