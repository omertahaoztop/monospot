/*
 * Monospot dashboard widget: portal servisi, zone, yetkili istemci ve 5651 özeti.
 * Veri: /api/monospot/service/info (configctl monospot info).
 */
export default class Monospot extends BaseWidget {
    getGridOptions() {
        /* özet tablo için makul başlangıç boyutu */
        return {
            w: 3,
            h: 3,
        };
    }

    getMarkup() {
        let rows = [
            ['portal_service', 'mp-service'],
            ['portal_address', 'mp-address'],
            ['zone', 'mp-zone'],
            ['zone_template', 'mp-template'],
            ['clients', 'mp-clients'],
            ['archive', 'mp-archive'],
        ].map(([label, id]) => `
            <tr>
                <th style="width: 45%">${this.translations[label] ?? label}</th>
                <td id="${id}">-</td>
            </tr>`).join('');

        return $(`
            <div id="monospot-widget">
                <table class="table table-condensed table-hover table-striped">
                    <tbody>${rows}</tbody>
                </table>
                <div id="mp-error" class="text-danger" style="display:none"></div>
                <div class="text-center">
                    <a href="/ui/monospot" class="btn btn-xs btn-default">${this.translations.settings ?? 'Ayarlar'}</a>
                    <a href="/ui/monospot/sessions" class="btn btn-xs btn-default">${this.translations.sessions ?? 'Oturumlar'}</a>
                    <a href="/5651/" target="_blank" class="btn btn-xs btn-default">5651</a>
                </div>
            </div>
        `);
    }

    badge(ok, text) {
        return `<span class="label label-opnsense label-opnsense-xs label-${ok ? 'success' : 'danger'}">${text}</span>`;
    }

    fail(message) {
        $('#mp-error').text(`${this.translations.unavailable ?? 'Durum okunamadı'}: ${message}`).show();
    }

    async onMarkupRendered() {
        try {
            const data = await this.ajaxCall('/api/monospot/service/info');
            if (!data || data.error) {
                this.fail((data && data.error) ? data.error : 'boş yanıt');
                return;
            }

            const port = `http://${window.location.hostname}:${data.portal_port}/`;
            $('#mp-service').html(this.badge(data.enabled,
                data.enabled ? (this.translations.enabled ?? 'etkin') : (this.translations.disabled ?? 'kapalı')));
            $('#mp-address').html(`<a href="${port}" target="_blank">${port}</a>`);
            $('#mp-zone').text(`${data.zoneid}${data.zone.description ? ' — ' + data.zone.description : ''}`
                + (data.zone.interfaces.length ? ` (${data.zone.interfaces.join(', ')})` : ''));

            const template = data.zone.template_name ?? '';
            $('#mp-template').html(template === ''
                ? this.badge(false, this.translations.not_installed ?? 'kurulmamış')
                : `${this.badge(true, template)} <a href="/ui/monospot/portal">→</a>`);

            $('#mp-clients').html(`${data.clients.authorized} <a href="/ui/monospot/sessions">→</a>`);
            $('#mp-archive').html(this.badge(data.collect5651,
                data.collect5651 ? (this.translations.enabled ?? 'etkin') : (this.translations.disabled ?? 'kapalı'))
                + ` — ${data.archive.days} ${this.translations.days ?? 'gün'}`);
        } catch (error) {
            this.fail((error && error.message) ? error.message : String(error));
        }
    }
}
