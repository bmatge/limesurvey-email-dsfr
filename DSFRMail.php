<?php

/**
 * DSFRMail Plugin for LimeSurvey
 *
 * Applique les templates d'email conformes au DSFR (Système de Design de l'État)
 * pour toutes les communications email de LimeSurvey.
 *
 * Basé sur https://github.com/GouvernementFR/dsfr-mail
 */
class DSFRMail extends PluginBase
{
    protected $storage = 'DbStorage';

    protected static $name = 'DSFRMail';
    protected static $description = 'Templates d\'emails conformes au DSFR pour LimeSurvey';

    /**
     * Configuration du plugin
     */
    protected $settings = [
        'enabled' => [
            'type' => 'select',
            'label' => 'Activer les templates DSFR',
            'options' => [
                '1' => 'Oui',
                '0' => 'Non'
            ],
            'default' => '1',
            'help' => 'Active ou désactive l\'application des templates DSFR aux emails'
        ],
        'primary_color' => [
            'type' => 'string',
            'label' => 'Couleur principale',
            'default' => '#000091',
            'help' => 'Couleur des liens et éléments d\'accentuation (bleu France par défaut)'
        ],
        'footer_text' => [
            'type' => 'text',
            'label' => 'Texte du pied de page',
            'default' => 'Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.',
            'help' => 'Texte affiché en bas de chaque email'
        ],
        'show_marianne' => [
            'type' => 'select',
            'label' => 'Afficher le logo Marianne',
            'options' => [
                '1' => 'Oui',
                '0' => 'Non'
            ],
            'default' => '1',
            'help' => 'Affiche le bloc-marque République Française'
        ],
        'logo_url' => [
            'type' => 'string',
            'label' => 'URL du logo personnalisé',
            'default' => '',
            'help' => 'URL complète vers un logo personnalisé (laissez vide pour utiliser Marianne)'
        ]
        // Note: Les éléments suivants se configurent par questionnaire via des tags dans le contenu de l'email :
        // - [ORGANIZATION_NAME]Nom de l'organisation[/ORGANIZATION_NAME]
        // - [ORGANIZATION_TAGLINE]Baseline[/ORGANIZATION_TAGLINE]
        // - [SERVICE_NAME]Nom du service[/SERVICE_NAME]
        // - [OPERATOR_LOGO]"url"|"alt"[/OPERATOR_LOGO]
    ];

    /**
     * Enregistrement des événements
     */
    public function init()
    {
        $this->subscribe('beforeEmail');
        $this->subscribe('beforeSurveyEmail');
        $this->subscribe('beforeTokenEmail');
    }

    /**
     * Intercepte les emails généraux
     */
    public function beforeEmail()
    {
        $this->wrapEmailContent();
    }

    /**
     * Intercepte les emails de questionnaire
     */
    public function beforeSurveyEmail()
    {
        $this->wrapEmailContent();
    }

    /**
     * Intercepte les emails de token (invitation, relance)
     */
    public function beforeTokenEmail()
    {
        $this->wrapEmailContent();
    }

    /**
     * Enveloppe le contenu de l'email dans le template DSFR
     */
    protected function wrapEmailContent()
    {
        if ($this->get('enabled', null, null, '1') !== '1') {
            return;
        }

        $event = $this->getEvent();
        $body = $event->get('body');
        $subject = $event->get('subject');

        if (empty($body)) {
            return;
        }

        // Générer le contenu avec le template DSFR
        $wrappedBody = $this->applyDSFRTemplate($body, $subject);

        // Remplacer le contenu
        $event->set('body', $wrappedBody);
    }

    /**
     * Applique le template DSFR au contenu
     *
     * @param string $content Contenu HTML de l'email
     * @param string $subject Sujet de l'email
     * @return string Contenu enveloppé dans le template DSFR
     */
    protected function applyDSFRTemplate($content, $subject = '')
    {
        // URL de base du plugin pour les assets
        $baseUrl = Yii::app()->getBaseUrl(true) . '/plugins/DSFRMail/assets/images/';

        // Extraire le logo opérateur du contenu (tag [OPERATOR_LOGO]) pour l'afficher dans le header
        // Cela permet de configurer le logo par questionnaire via le contenu de l'email
        $operatorLogoBlock = '';
        $extractResult = $this->extractOperatorLogo($content);
        if ($extractResult !== null) {
            $operatorLogoBlock = $extractResult['html'];
            $content = $extractResult['content']; // Contenu sans le tag [OPERATOR_LOGO]
        }

        // Extraire le nom du service du contenu (tag [SERVICE_NAME]) pour l'afficher dans le header
        $serviceName = '';
        $serviceResult = $this->extractServiceName($content);
        if ($serviceResult !== null) {
            $serviceName = $serviceResult['value'];
            $content = $serviceResult['content']; // Contenu sans le tag [SERVICE_NAME]
        }

        // Extraire le nom de l'organisation du contenu (tag [ORGANIZATION_NAME])
        $organizationName = '';
        $orgNameResult = $this->extractSimpleTag($content, 'ORGANIZATION_NAME');
        if ($orgNameResult !== null) {
            $organizationName = $orgNameResult['value'];
            $content = $orgNameResult['content'];
        }

        // Extraire la baseline de l'organisation du contenu (tag [ORGANIZATION_TAGLINE])
        $organizationTagline = '';
        $orgTaglineResult = $this->extractSimpleTag($content, 'ORGANIZATION_TAGLINE');
        if ($orgTaglineResult !== null) {
            $organizationTagline = $orgTaglineResult['value'];
            $content = $orgTaglineResult['content'];
        }

        // Transformer les autres balises DSFR en composants HTML
        $content = $this->transformDSFRTags($content);

        $config = [
            'organization_name' => $organizationName,
            'organization_tagline' => $organizationTagline,
            'service_name' => $serviceName,
            'primary_color' => $this->get('primary_color', null, null, '#000091'),
            'footer_text' => $this->get('footer_text', null, null, 'Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.'),
            'show_marianne' => $this->get('show_marianne', null, null, '1') === '1',
            'logo_url' => $this->get('logo_url', null, null, ''),
            'logo_url_light' => $baseUrl . 'Marianne-Light@2x.png',
            'logo_url_dark' => $baseUrl . 'Marianne-Dark@2x.png',
            'operator_logo_block' => $operatorLogoBlock,
            'subject' => $subject,
            'email_title' => $subject,
            'content' => $content,
            'year' => date('Y')
        ];

        return $this->renderTemplate('base', $config);
    }

    /**
     * Extrait le tag [OPERATOR_LOGO] du contenu pour l'afficher dans le header
     *
     * Cette méthode permet de configurer le logo opérateur par questionnaire
     * en l'incluant dans le contenu de l'email avec le format :
     * [OPERATOR_LOGO]"url"|"alt"[/OPERATOR_LOGO]
     *
     * @param string $content Contenu de l'email
     * @return array|null ['html' => HTML du bloc, 'content' => contenu sans le tag] ou null si pas de tag
     */
    protected function extractOperatorLogo($content)
    {
        // Pattern pour [OPERATOR_LOGO]"src"|"alt"[/OPERATOR_LOGO]
        // Accepte " ou &quot; ou guillemets typographiques " "
        $pattern = '/\[OPERATOR_LOGO\](?:"|&quot;|"|")(.+?)(?:"|&quot;|"|")\|(?:"|&quot;|"|")(.+?)(?:"|&quot;|"|")\[\/OPERATOR_LOGO\]/s';

        if (preg_match($pattern, $content, $matches)) {
            $src = trim($matches[1]);
            $alt = trim($matches[2]);

            // Générer le HTML du bloc logo
            $html = $this->renderOperatorLogoBlock($src, $alt);

            // Supprimer le tag du contenu
            $contentWithoutTag = preg_replace($pattern, '', $content);

            return [
                'html' => $html,
                'content' => $contentWithoutTag
            ];
        }

        return null;
    }

    /**
     * Extrait le tag [SERVICE_NAME] du contenu pour l'afficher dans le header
     *
     * @param string $content Contenu de l'email
     * @return array|null ['value' => nom du service, 'content' => contenu sans le tag] ou null si pas de tag
     */
    protected function extractServiceName($content)
    {
        return $this->extractSimpleTag($content, 'SERVICE_NAME');
    }

    /**
     * Extrait un tag simple du contenu
     *
     * Cette méthode générique permet d'extraire des tags de la forme :
     * [TAG_NAME]valeur[/TAG_NAME]
     *
     * Utilisé pour : SERVICE_NAME, ORGANIZATION_NAME, ORGANIZATION_TAGLINE
     *
     * @param string $content Contenu de l'email
     * @param string $tagName Nom du tag (sans crochets)
     * @return array|null ['value' => valeur, 'content' => contenu sans le tag] ou null si pas de tag
     */
    protected function extractSimpleTag($content, $tagName)
    {
        // Pattern pour [TAG_NAME]...[/TAG_NAME]
        $pattern = '/\[' . preg_quote($tagName, '/') . '\](.*?)\[\/' . preg_quote($tagName, '/') . '\]/s';

        if (preg_match($pattern, $content, $matches)) {
            $value = trim($matches[1]);

            // Supprimer le tag du contenu
            $contentWithoutTag = preg_replace($pattern, '', $content);

            return [
                'value' => $value,
                'content' => $contentWithoutTag
            ];
        }

        return null;
    }

    /**
     * Transforme les balises DSFR en composants HTML email
     *
     * Balises supportées :
     * - [INFO]...[/INFO] : Callout bleu (information)
     * - [SUCCESS]...[/SUCCESS] : Callout vert (succès)
     * - [WARNING]...[/WARNING] : Callout orange (avertissement)
     * - [ERROR]...[/ERROR] : Callout rouge (erreur)
     * - [HIGHLIGHT]...[/HIGHLIGHT] : Bloc gris (mise en avant)
     * - [QUOTE]...[/QUOTE] : Citation avec bordure bleue
     * - [BUTTON]url|texte[/BUTTON] : Bouton DSFR bleu
     * - [BUTTON_SECONDARY]url|texte[/BUTTON_SECONDARY] : Bouton secondaire
     * - [MUTED]...[/MUTED] : Texte gris discret
     * - [TITLE]...[/TITLE] : Titre principal
     * - [LINK]url|texte[/LINK] : Lien stylé DSFR
     *
     * Note: [OPERATOR_LOGO], [SERVICE_NAME], [ORGANIZATION_NAME] et [ORGANIZATION_TAGLINE]
     * sont traités séparément pour le header (voir extractOperatorLogo, extractSimpleTag)
     *
     * @param string $content Contenu avec balises
     * @return string Contenu transformé en HTML
     */
    protected function transformDSFRTags($content)
    {
        // [TITLE]...[/TITLE] - Titre principal
        $content = preg_replace_callback(
            '/\[TITLE\](.*?)\[\/TITLE\]/s',
            function ($m) {
                return '<h1 style="margin: 0 0 24px 0; font-size: 24px; font-weight: 700; color: #161616; line-height: 1.3; font-family: \'Marianne\', Arial, sans-serif;">' . trim($m[1]) . '</h1>';
            },
            $content
        );

        // [INFO]...[/INFO] - Callout bleu
        $content = preg_replace_callback(
            '/\[INFO\](.*?)\[\/INFO\]/s',
            function ($m) {
                return $this->renderCallout($m[1], 'info');
            },
            $content
        );

        // [SUCCESS]...[/SUCCESS] - Callout vert
        $content = preg_replace_callback(
            '/\[SUCCESS\](.*?)\[\/SUCCESS\]/s',
            function ($m) {
                return $this->renderCallout($m[1], 'success');
            },
            $content
        );

        // [WARNING]...[/WARNING] - Callout orange
        $content = preg_replace_callback(
            '/\[WARNING\](.*?)\[\/WARNING\]/s',
            function ($m) {
                return $this->renderCallout($m[1], 'warning');
            },
            $content
        );

        // [ERROR]...[/ERROR] - Callout rouge
        $content = preg_replace_callback(
            '/\[ERROR\](.*?)\[\/ERROR\]/s',
            function ($m) {
                return $this->renderCallout($m[1], 'error');
            },
            $content
        );

        // [HIGHLIGHT]...[/HIGHLIGHT] - Bloc gris
        $content = preg_replace_callback(
            '/\[HIGHLIGHT\](.*?)\[\/HIGHLIGHT\]/s',
            function ($m) {
                return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;"><tr><td style="padding: 16px 20px; background-color: #f6f6f6; border-radius: 4px; font-size: 15px; line-height: 1.6; color: #161616; font-family: \'Marianne\', Arial, sans-serif;">' . trim($m[1]) . '</td></tr></table>';
            },
            $content
        );

        // [QUOTE]...[/QUOTE] - Citation
        $content = preg_replace_callback(
            '/\[QUOTE\](.*?)\[\/QUOTE\]/s',
            function ($m) {
                return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;"><tr><td style="padding: 12px 20px; border-left: 4px solid #000091; font-size: 15px; font-style: italic; line-height: 1.6; color: #666666; font-family: \'Marianne\', Arial, sans-serif;">' . trim($m[1]) . '</td></tr></table>';
            },
            $content
        );

        // [BUTTON]url|texte[/BUTTON] - Bouton primaire
        $content = preg_replace_callback(
            '/\[BUTTON\](.*?)\|(.*?)\[\/BUTTON\]/s',
            function ($m) {
                $url = trim($m[1]);
                $text = trim($m[2]);
                return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;"><tr><td style="background-color: #000091; border-radius: 0;"><a href="' . htmlspecialchars($url) . '" target="_blank" style="display: inline-block; padding: 12px 24px; font-size: 16px; font-weight: 500; color: #ffffff; text-decoration: none; font-family: \'Marianne\', Arial, sans-serif;">' . htmlspecialchars($text) . '</a></td></tr></table>';
            },
            $content
        );

        // [BUTTON_SECONDARY]url|texte[/BUTTON_SECONDARY] - Bouton secondaire
        $content = preg_replace_callback(
            '/\[BUTTON_SECONDARY\](.*?)\|(.*?)\[\/BUTTON_SECONDARY\]/s',
            function ($m) {
                $url = trim($m[1]);
                $text = trim($m[2]);
                return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;"><tr><td style="background-color: #ffffff; border: 1px solid #000091; border-radius: 0;"><a href="' . htmlspecialchars($url) . '" target="_blank" style="display: inline-block; padding: 11px 23px; font-size: 16px; font-weight: 500; color: #000091; text-decoration: none; font-family: \'Marianne\', Arial, sans-serif;">' . htmlspecialchars($text) . '</a></td></tr></table>';
            },
            $content
        );

        // [MUTED]...[/MUTED] - Texte discret
        $content = preg_replace_callback(
            '/\[MUTED\](.*?)\[\/MUTED\]/s',
            function ($m) {
                return '<p style="margin: 16px 0; font-size: 13px; color: #666666; line-height: 1.5; font-family: \'Marianne\', Arial, sans-serif;">' . trim($m[1]) . '</p>';
            },
            $content
        );

        // [LINK]url|texte[/LINK] - Lien stylé
        $content = preg_replace_callback(
            '/\[LINK\](.*?)\|(.*?)\[\/LINK\]/s',
            function ($m) {
                $url = trim($m[1]);
                $text = trim($m[2]);
                return '<a href="' . htmlspecialchars($url) . '" style="color: #000091; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
            },
            $content
        );

        // Note: [OPERATOR_LOGO] est traité par extractOperatorLogo() avant cette méthode
        // pour être affiché dans le header de l'email, pas dans le body

        return $content;
    }

    /**
     * Génère un callout HTML email
     *
     * @param string $content Contenu du callout
     * @param string $type Type: info, success, warning, error
     * @return string HTML du callout
     */
    protected function renderCallout($content, $type)
    {
        $colors = [
            'info' => ['bg' => '#e8edff', 'border' => '#000091', 'icon' => 'ℹ️', 'title' => 'Information'],
            'success' => ['bg' => '#b8fec9', 'border' => '#18753c', 'icon' => '✓', 'title' => 'Succès'],
            'warning' => ['bg' => '#ffe9e6', 'border' => '#b34000', 'icon' => '⚠', 'title' => 'Attention'],
            'error' => ['bg' => '#ffe9e9', 'border' => '#ce0500', 'icon' => '✕', 'title' => 'Erreur'],
        ];

        $c = $colors[$type] ?? $colors['info'];
        $text = trim($content);

        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;"><tr><td style="padding: 16px 20px; background-color: ' . $c['bg'] . '; border-left: 4px solid ' . $c['border'] . '; font-size: 15px; line-height: 1.6; color: #161616; font-family: \'Marianne\', Arial, sans-serif;"><strong style="color: ' . $c['border'] . ';">' . $c['icon'] . ' ' . $c['title'] . '</strong><br>' . $text . '</td></tr></table>';
    }

    /**
     * Génère le bloc logo opérateur avec séparateur vertical
     *
     * @param string $src URL ou base64 de l'image
     * @param string $alt Texte alternatif pour l'accessibilité
     * @return string HTML du bloc opérateur
     */
    protected function renderOperatorLogoBlock($src, $alt)
    {
        $altEscaped = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

        return '
                          <table style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;"
                            width="120" cellspacing="0" cellpadding="0" role="presentation" border="0" align="left"
                            bgcolor="#ffffff" class="darkmode">
                            <tr>
                              <td align="center" valign="middle" style="padding: 8px 0;">
                                <table style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;"
                                  width="100%" cellspacing="0" cellpadding="0" role="presentation" border="0"
                                  align="left" bgcolor="#ffffff" class="darkmode">
                                  <tr>
                                    <!-- Séparateur vertical -->
                                    <td width="12" align="center" valign="middle" style="padding: 0 6px;">
                                      <!--[if mso]>
                                      <table role="presentation" width="1" height="50" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                          <td bgcolor="#e5e5e5" width="1" height="50" style="width:1px; height:50px;"></td>
                                        </tr>
                                      </table>
                                      <![endif]-->
                                      <!--[if !mso]><!-->
                                      <table style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;"
                                        width="1" height="50" cellspacing="0" cellpadding="0" role="presentation" border="0">
                                        <tr>
                                          <td bgcolor="#e5e5e5" width="1" height="50" style="width:1px; height:50px; background-color:#e5e5e5;">&nbsp;</td>
                                        </tr>
                                      </table>
                                      <!--<![endif]-->
                                    </td>
                                    <!-- Logo opérateur -->
                                    <td align="left" valign="middle" style="padding-left: 8px;">
                                      <img src="' . $src . '" alt="' . $altEscaped . '"
                                        style="display: block; height: auto; max-height: 50px; max-width: 80px; width: auto;"
                                        border="0">
                                    </td>
                                  </tr>
                                </table>
                              </td>
                            </tr>
                          </table>';
    }

    /**
     * Charge et rend un template
     *
     * @param string $templateName Nom du template (sans extension)
     * @param array $data Données à injecter
     * @return string HTML rendu
     */
    protected function renderTemplate($templateName, $data)
    {
        $templatePath = dirname(__FILE__) . '/templates/' . $templateName . '.html';

        if (!file_exists($templatePath)) {
            return $data['content'] ?? '';
        }

        $template = file_get_contents($templatePath);

        // D'abord remplacer le contenu HTML (sans échappement)
        if (isset($data['content'])) {
            $template = str_replace('{{content}}', $data['content'], $template);
        }

        // Puis remplacer les autres variables (avec échappement)
        foreach ($data as $key => $value) {
            if ($key === 'content') {
                continue; // Déjà traité
            }
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace('{{' . $key . '}}', $value, $template);
            }
        }

        return $template;
    }

    /**
     * Génère le bloc-marque Marianne en Base64 SVG
     *
     * @return string SVG encodé en data URI
     */
    public static function getMarianneSVG()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 80">
            <rect width="160" height="80" fill="#fff"/>
            <rect x="0" y="0" width="8" height="80" fill="#000091"/>
            <rect x="8" y="0" width="8" height="80" fill="#fff"/>
            <rect x="16" y="0" width="8" height="80" fill="#E1000F"/>
            <text x="35" y="28" font-family="Marianne, Arial, sans-serif" font-size="11" font-weight="bold" fill="#000091">RÉPUBLIQUE</text>
            <text x="35" y="42" font-family="Marianne, Arial, sans-serif" font-size="11" font-weight="bold" fill="#000091">FRANÇAISE</text>
            <text x="35" y="60" font-family="Marianne, Arial, sans-serif" font-size="7" fill="#161616">Liberté</text>
            <text x="35" y="68" font-family="Marianne, Arial, sans-serif" font-size="7" fill="#161616">Égalité</text>
            <text x="35" y="76" font-family="Marianne, Arial, sans-serif" font-size="7" fill="#161616">Fraternité</text>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
