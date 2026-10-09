{#
 # Monospot > SMS Raporu (salt-okunur liste).
 #}
<script>
    'use strict';

    $(document).ready(function () {
        $("#{{formGridSms['table_id']}}").UIBootgrid({
            search: '/api/monospot/reports/sms/',
            options: {
                templates: {
                    select: ''
                }
            }
        });
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridSms)}}
</div>
