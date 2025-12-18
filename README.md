# DSFRMail - Plugin LimeSurvey

Plugin LimeSurvey pour générer des emails conformes au **Système de Design de l'État (DSFR)**.

> **Basé sur [DSFR Mail](https://github.com/GouvernementFR/dsfr-mail)** - Templates d'emails officiels du Gouvernement Français.
> Merci à l'équipe du DSFR pour ce travail de qualité !

![Exemple d'email DSFR](assets/images/Screenshot.png)

## Fonctionnalités

- Template d'email responsive compatible tous clients mail
- Bloc-marque République Française intégré
- Logo opérateur configurable par questionnaire
- Couleurs DSFR (bleu France #000091)
- Support du mode sombre
- **Balises DSFR** pour styliser le contenu des emails
- Configuration par questionnaire via des tags

## Installation

1. Copiez le dossier `DSFRMail` dans `/plugins/` de votre installation LimeSurvey
2. Activez le plugin dans **Configuration > Plugins**

## Configuration globale

Dans les paramètres du plugin :

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| Activer les templates DSFR | Active/désactive le plugin | Oui |
| Couleur principale | Couleur des liens et accents | `#000091` (bleu France) |
| Texte du pied de page | Message affiché en footer | "Cet email a été envoyé automatiquement..." |
| Afficher le logo Marianne | Affiche le bloc-marque RF | Oui |
| URL du logo personnalisé | Remplace le logo Marianne | (vide) |

## Configuration par questionnaire

Ces éléments se configurent **par questionnaire** en ajoutant des tags dans le contenu de l'email (invitation, rappel, confirmation...). Les tags sont extraits du contenu et affichés dans le header.

### Tags pour le header

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  [Logo Marianne]  │  [Logo opérateur]      [Nom du service]    │
│  Nom organisation │                                             │
│  Baseline         │                                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

| Tag | Description | Exemple |
|-----|-------------|---------|
| `[ORGANIZATION_NAME]...[/ORGANIZATION_NAME]` | Nom de l'organisation (sous le logo) | `[ORGANIZATION_NAME]République Française[/ORGANIZATION_NAME]` |
| `[ORGANIZATION_TAGLINE]...[/ORGANIZATION_TAGLINE]` | Baseline (sous le nom) | `[ORGANIZATION_TAGLINE]Liberté Égalité Fraternité[/ORGANIZATION_TAGLINE]` |
| `[SERVICE_NAME]...[/SERVICE_NAME]` | Nom du service (aligné à droite) | `[SERVICE_NAME]Direction des enquêtes[/SERVICE_NAME]` |
| `[OPERATOR_LOGO]"url"\|"alt"[/OPERATOR_LOGO]` | Logo opérateur (à côté de Marianne) | Voir exemple ci-dessous |

**Exemple de logo opérateur :**
```
[OPERATOR_LOGO]"https://example.com/logo-ministere.png"|"Ministère de l'Économie"[/OPERATOR_LOGO]
```

> **Note :** Les tags du header sont automatiquement extraits du contenu et n'apparaissent pas dans le corps de l'email.

### Tags pour le contenu

#### Callouts (alertes)

| Tag | Description | Couleur |
|-----|-------------|---------|
| `[INFO]...[/INFO]` | Information | Bleu |
| `[SUCCESS]...[/SUCCESS]` | Succès | Vert |
| `[WARNING]...[/WARNING]` | Avertissement | Orange |
| `[ERROR]...[/ERROR]` | Erreur | Rouge |

```
[INFO]Votre numéro de dossier : 2024-12345[/INFO]

[SUCCESS]Votre inscription a été confirmée ![/SUCCESS]

[WARNING]Attention : ce questionnaire expire dans 3 jours.[/WARNING]

[ERROR]Une erreur est survenue lors de l'envoi.[/ERROR]
```

#### Mise en forme

| Tag | Description |
|-----|-------------|
| `[TITLE]...[/TITLE]` | Titre principal (24px, gras) |
| `[HIGHLIGHT]...[/HIGHLIGHT]` | Bloc mise en avant (fond gris) |
| `[QUOTE]...[/QUOTE]` | Citation (bordure gauche bleue) |
| `[MUTED]...[/MUTED]` | Texte discret (gris, petite taille) |

```
[TITLE]Confirmation de votre participation[/TITLE]

[HIGHLIGHT]
Prochaines étapes :
- Un agent traitera votre demande sous 5 jours
- Vous recevrez une notification par email
[/HIGHLIGHT]

[QUOTE]« Pensez à conserver ce mail pour vos archives »[/QUOTE]

[MUTED]Si vous ne souhaitez plus recevoir de messages : {OPTOUTURL}[/MUTED]
```

#### Boutons et liens

| Tag | Description |
|-----|-------------|
| `[BUTTON]url\|texte[/BUTTON]` | Bouton principal (bleu plein) |
| `[BUTTON_SECONDARY]url\|texte[/BUTTON_SECONDARY]` | Bouton secondaire (contour bleu) |
| `[LINK]url\|texte[/LINK]` | Lien stylé DSFR |

```
[BUTTON]{SURVEYURL}|Participer au questionnaire[/BUTTON]

[BUTTON_SECONDARY]{OPTOUTURL}|Se désinscrire[/BUTTON_SECONDARY]

[LINK]https://example.com|En savoir plus[/LINK]
```

## Exemple complet

Corps d'email d'invitation avec tous les tags :

```
[ORGANIZATION_NAME]République Française[/ORGANIZATION_NAME]
[ORGANIZATION_TAGLINE]Liberté Égalité Fraternité[/ORGANIZATION_TAGLINE]
[SERVICE_NAME]Direction des statistiques[/SERVICE_NAME]
[OPERATOR_LOGO]"https://example.com/logo-ministere.png"|"Ministère de l'Économie"[/OPERATOR_LOGO]

[TITLE]Invitation à participer[/TITLE]

Bonjour {FIRSTNAME} {LASTNAME},

Vous êtes invité(e) à participer au questionnaire "{SURVEYNAME}".

[HIGHLIGHT]
{SURVEYDESCRIPTION}
[/HIGHLIGHT]

[INFO]Durée estimée : 5 minutes[/INFO]

[BUTTON]{SURVEYURL}|Répondre au questionnaire[/BUTTON]

Cordialement,
{ADMINNAME}

[MUTED]
Si vous ne souhaitez pas participer :
[LINK]{OPTOUTURL}|Se désinscrire[/LINK]
[/MUTED]
```

## Compatibilité

- LimeSurvey 5.x et 6.x
- Clients email : Gmail, Outlook, Apple Mail, Thunderbird
- Mode sombre supporté automatiquement

## Ressources

- [DSFR - Système de Design de l'État](https://www.systeme-de-design.gouv.fr/)
- [DSFR Mail](https://github.com/GouvernementFR/dsfr-mail)

## Licence

GNU General Public License version 2 or later
