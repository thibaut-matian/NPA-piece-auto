<?php

defined('ABSPATH') || exit;

class NPA_Pickup_Email extends WC_Email
{
    public function __construct()
    {
        $this->id = 'npa_pickup_ready';
        $this->customer_email = true;
        $this->title = 'Commande prête à retirer';
        $this->description = 'Envoyé lorsque l’équipe marque un retrait magasin comme prêt. Le délai de retrait est de sept jours calendaires.';
        $this->placeholders = ['{order_number}' => ''];
        parent::__construct();
        add_action('woocommerce_order_status_npa-ready_notification', [$this, 'trigger'], 10, 2);
    }

    public function get_default_subject()
    {
        return '[{site_title}] Votre commande n°{order_number} est prête à retirer';
    }

    public function get_default_heading()
    {
        return 'Votre commande est prête à retirer';
    }

    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->form_fields['pickup_address'] = [
            'title'       => 'Adresse de retrait',
            'type'        => 'textarea',
            'description' => 'Adresse du comptoir à vérifier avant activation des envois.',
            'default'     => '158 Avenue de la Capelette, 13010 Marseille',
        ];
        $this->form_fields['pickup_hours'] = [
            'title'       => 'Horaires de retrait',
            'type'        => 'textarea',
            'description' => 'À vérifier avant activation des envois. Présents dans l’e-mail client.',
            'default'     => 'Lun - Ven : 08h30 - 12h30 / 14h00 - 18h30 ; Sam : 09h00 - 13h00',
        ];
    }

    public function trigger($order_id, $order = false)
    {
        $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        if (!$order || !npa_pickup_details($order)) {
            wc_get_logger()->warning('E-mail de retrait refusé : commande non prête ou sans retrait magasin.', [
                'source' => 'npa-pickup', 'order_id' => $order_id,
            ]);
            return;
        }
        $this->setup_locale();
        try {
            $this->object = $order;
            $this->recipient = $order->get_billing_email();
            $this->placeholders['{order_number}'] = $order->get_order_number();
            if (!$this->is_enabled()) {
                $order->add_order_note('E-mail « Prête à retirer » non envoyé : notification désactivée.');
                return;
            }
            if (!$this->get_recipient() || !$this->send(
                $this->get_recipient(), $this->get_subject(), $this->get_content(),
                $this->get_headers(), $this->get_attachments()
            )) {
                $order->add_order_note('Échec de l’envoi « Prête à retirer ». Vérifiez les journaux WooCommerce puis utilisez l’action de renvoi.');
                wc_get_logger()->error('Échec de notification de retrait.', ['source' => 'npa-pickup', 'order_id' => $order_id]);
                return;
            }
            $order->add_order_note('E-mail « Prête à retirer » accepté par le service d’envoi. La remise au destinataire reste à vérifier.');
        } finally {
            $this->restore_locale();
        }
    }

    private function message_lines()
    {
        $pickup = $this->object instanceof WC_Order ? npa_pickup_details($this->object) : null;
        if (!$pickup) {
            return ['Aperçu indisponible : sélectionnez une commande prête à retirer avec une date limite valide.'];
        }
        return [
            sprintf('Votre commande n°%s est prête à être récupérée en magasin.', $this->object->get_order_number()),
            'Adresse de retrait : ' . $this->get_option('pickup_address'),
            'Horaires : ' . $this->get_option('pickup_hours'),
            'Vous disposez de 7 jours calendaires. Retrait avant le ' . wp_date('d/m/Y à H:i', strtotime($pickup['deadline'])) . ' (heure du magasin).',
            'Présentez votre numéro de commande lors du retrait.',
            $this->object->get_payment_method() === 'cod' && !$this->object->get_date_paid()
                ? 'Votre commande reste à régler au comptoir lors du retrait.'
                : '',
            'Après cette date, contactez le magasin : aucune annulation ni aucun remboursement ne sont automatiques.',
        ];
    }

    public function get_content_html()
    {
        $html = wc_get_template_html('emails/email-header.php', ['email_heading' => $this->get_heading(), 'email' => $this]);
        foreach (array_filter($this->message_lines()) as $line) {
            $html .= '<p>' . esc_html($line) . '</p>';
        }
        return $html . wc_get_template_html('emails/email-footer.php', ['email' => $this]);
    }

    public function get_content_plain()
    {
        return implode("\n\n", array_filter($this->message_lines())) . "\n";
    }
}
