{#
 # Monospot > İzin Raporu (salt-okunur KVKK kayıtları).
 #}
<script>
    'use strict';

    $(document).ready(function () {
        $("#{{formGridPermission['table_id']}}").UIBootgrid({
            search: '/api/monospot/reports/permission/',
            options: {
                templates: {
                    select: ''
                }
            }
        });
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridPermission)}}
</div>
