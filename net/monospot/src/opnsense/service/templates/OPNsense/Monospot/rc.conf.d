{% if helpers.exists('OPNsense.Monospot.general.enabled') and OPNsense.Monospot.general.enabled|default('0') == '1' %}
monospot_enable="YES"
{% else %}
monospot_enable="NO"
{% endif %}
