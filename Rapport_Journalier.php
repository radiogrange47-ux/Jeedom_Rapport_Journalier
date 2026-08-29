// ----- BLOC 1 - ACQUISITION DES DONNEES -----

$rapport = array();

// CONFIGURATION

$nbJoursHistorique = 5;

$rapport['configuration'] = array(
    'nbJoursHistorique' => $nbJoursHistorique,

    'seuilGel' => 0,
    'seuilFroid' => 5,
    'seuilForteChaleur' => 35,
    'seuilCanicule' => 39,

    'seuilVariationStable' => 2,
    'seuilVariationImportante' => 20,
    'seuilVariationMeteo' => 0.5,
    'seuilEcartTemperature' => 8
);

// FONCTIONS COMMUNES

if (!function_exists('rapportNomJour')) {
    function rapportNomJour($timestamp) {
        $jours = array(
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        );

        $jour = date('l', $timestamp);
        return isset($jours[$jour]) ? $jours[$jour] : $jour;
    }
}

if (!function_exists('rapportCmdExec')) {
    function rapportCmdExec($commande, $defaut = null) {
        $cmd = cmd::byString($commande);

        if (!is_object($cmd)) {
            return $defaut;
        }

        return $cmd->execCmd();
    }
}

if (!function_exists('rapportAjouterAlerte')) {
    function rapportAjouterAlerte(&$rapport, $niveau, $type, $message, $origine = null) {
        if (!isset($rapport['alertes']) || !is_array($rapport['alertes'])) {
            $rapport['alertes'] = array();
        }

        $alerte = array(
            'niveau' => $niveau,
            'type' => $type,
            'message' => $message
        );

        if ($origine !== null) {
            $alerte['origine'] = $origine;
        }

        $rapport['alertes'][] = $alerte;
    }
}

// PERIODE DU RAPPORT

$rapport['periode'] = array(
    'generation' => date('Y-m-d H:i:s'),
    'debut' => date('Y-m-d 00:00:00', strtotime('-'.$nbJoursHistorique.' days')),
    'fin' => date('Y-m-d 23:59:59', strtotime('-1 day'))
);

// SOLEIL

$rapport['soleil'] = array(
    'lever' => rapportCmdExec('#[SalonCuisine][Commandes Volets][Lever Soleil]#', ''),
    'coucher' => rapportCmdExec('#[SalonCuisine][Commandes Volets][Coucher Soleil]#', '')
);

// HISTORIQUES

$rapport['historique'] = array();

$historiques = array(
    'electricite' => 84,
    'eau' => 4204,
    'chauffage' => 639
);

foreach ($historiques as $nom => $idCmd) {
    $cmd = cmd::byId($idCmd);
    $rapport['historique'][$nom] = array();

    if (!is_object($cmd)) {
        rapportAjouterAlerte($rapport, 'warning', 'configuration', 'Commande historique introuvable : '.$nom, $nom);
        continue;
    }

    $history = $cmd->getHistory($rapport['periode']['debut'], $rapport['periode']['fin']);

    foreach ($history as $h) {
        $date = date('d/m/Y', strtotime($h->getDatetime()));
        $valeur = floatval($h->getValue());

        if (!isset($rapport['historique'][$nom][$date])) {
            $rapport['historique'][$nom][$date] = array(
                'datetime' => $h->getDatetime(),
                'date' => $date,
                'valeur' => 0
            );
        }

        $rapport['historique'][$nom][$date]['valeur'] += $valeur;
    }

    $rapport['historique'][$nom] = array_values($rapport['historique'][$nom]);
}

// PREVISIONS METEO

$rapport['meteo'] = array();

for ($i = 0; $i <= 4; $i++) {
    $suffixe = $i === 0 ? '' : ' +'.$i;
    $cle = $i === 0 ? 'aujourdhui' : 'j'.$i;
    $timestamp = strtotime('+'.$i.' day');

    $rapport['meteo'][$cle] = array(
        'jour' => $i === 0 ? "Aujourd'hui" : rapportNomJour($timestamp),
        'date' => date('d/m/Y', $timestamp),
        'temp_min' => rapportCmdExec('#[Extérieur][Gagny][Température Min'.$suffixe.']#', 0),
        'temp_max' => rapportCmdExec('#[Extérieur][Gagny][Température Max'.$suffixe.']#', 0),
        'condition' => rapportCmdExec('#[Extérieur][Gagny][Condition'.$suffixe.']#', '')
    );
}

// TEMPERATURES ACTUELLES

$rapport['temperatures'] = array(
    'portail' => rapportCmdExec('#[Garage et Exterieur][ESP_Commande Portail][Temperature]#', 0),
    'cabanon' => rapportCmdExec('#[Gestions Plantes][ESP_Cuve A Pluie][Temperature]#', 0),
    'mychambre' => rapportCmdExec('#[Equipements ZigBee][Sonde_d5:d2:1c (MyChambre)][Température]#', 0),
    'sdbhaut' => rapportCmdExec('#[Equipements ZigBee][Sonde_d5:9d:74 (SdBHaut)][Température]#', 0)
);

// MESSAGES JEEDOM

$rapport['messages'] = array();

foreach (message::all() as $message) {
    $rapport['messages'][] = array(
        'date' => $message->getDate(),
        'plugin' => $message->getPlugin(),
        'action' => $message->getAction(),
        'message' => $message->getMessage()
    );
}

$scenario->setTags(array(
    'rapport' => json_encode($rapport, JSON_UNESCAPED_UNICODE)
));

// ----- BLOC 2 - TRAITEMENTS METEO, SOLEIL, HISTORIQUES, TEMPERATURES -----

$rapport = json_decode($scenario->getTag('rapport'), true);

if (!is_array($rapport)) {
    $scenario->setLog("Impossible de récupérer le rapport.");
    return;
}

if (!isset($rapport['alertes'])) {
    $rapport['alertes'] = array();
}

$rapport['comparaisons'] = array();
$rapport['statistiques'] = array();

// SOLEIL

$lever = str_replace('h', ':', $rapport['soleil']['lever']);
$coucher = str_replace('h', ':', $rapport['soleil']['coucher']);
$heureLever = strtotime($lever);
$heureCoucher = strtotime($coucher);

if ($heureLever !== false && $heureCoucher !== false && $heureCoucher > $heureLever) {
    $duree = $heureCoucher - $heureLever;
    $rapport['soleil']['duree'] = sprintf('%02dh%02d', floor($duree / 3600), floor(($duree % 3600) / 60));
} else {
    $rapport['soleil']['duree'] = 'Inconnue';
}

// TENDANCES METEO

$rapport['comparaisons']['meteo'] = array();
$moyenneVeille = null;

foreach ($rapport['meteo'] as $meteo) {
    $moyenne = round((floatval($meteo['temp_min']) + floatval($meteo['temp_max'])) / 2, 1);
    $variation = null;
    $tendance = null;

    if ($moyenneVeille !== null) {
        $variation = round($moyenne - $moyenneVeille, 1);

        if ($variation > $rapport['configuration']['seuilVariationMeteo']) {
            $tendance = 1;
        } elseif ($variation < -$rapport['configuration']['seuilVariationMeteo']) {
            $tendance = -1;
        } else {
            $tendance = 0;
        }
    }

    $rapport['comparaisons']['meteo'][] = array(
        'jour' => $meteo['jour'],
        'date' => $meteo['date'],
        'moyenne' => $moyenne,
        'variation' => $variation,
        'tendance' => $tendance
    );

    $moyenneVeille = $moyenne;
}

foreach ($rapport['meteo'] as $cle => $meteo) {
    $mini = floatval($meteo['temp_min']);
    $maxi = floatval($meteo['temp_max']);
    $condition = mb_strtolower($meteo['condition'], 'UTF-8');
    $libelleJour = $meteo['jour'].' '.$meteo['date'];

    if ($mini < $rapport['configuration']['seuilGel']) {
        rapportAjouterAlerte($rapport, 'danger', 'meteo', 'Gel prévu '.$libelleJour);
    } elseif ($mini < $rapport['configuration']['seuilFroid']) {
        rapportAjouterAlerte($rapport, 'warning', 'meteo', 'Froid prévu '.$libelleJour);
    }

    if ($maxi > $rapport['configuration']['seuilCanicule']) {
        rapportAjouterAlerte($rapport, 'danger', 'meteo', 'Canicule '.$libelleJour);
    } elseif ($maxi > $rapport['configuration']['seuilForteChaleur']) {
        rapportAjouterAlerte($rapport, 'warning', 'meteo', 'Forte chaleur '.$libelleJour);
    }

    if (strpos($condition, 'pluie') !== false || strpos($condition, 'averse') !== false) {
        rapportAjouterAlerte($rapport, 'info', 'meteo', 'Pluie '.$libelleJour);
    }

    if (strpos($condition, 'orage') !== false) {
        rapportAjouterAlerte($rapport, 'danger', 'meteo', 'Orage '.$libelleJour);
    }

    if (strpos($condition, 'vent') !== false) {
        rapportAjouterAlerte($rapport, 'warning', 'meteo', 'Vent '.$libelleJour);
    }
}

// HISTORIQUES, VARIATIONS ET STATISTIQUES

foreach ($rapport['historique'] as $nom => $historique) {
    $valeurVeille = null;
    $total = 0;
    $minimum = null;
    $maximum = null;

    foreach ($historique as $index => $jour) {
        $valeur = round(floatval($jour['valeur']), 3);
        $variation = null;
        $tendance = null;

        if ($valeurVeille !== null && $valeurVeille != 0) {
            $variation = round((($valeur - $valeurVeille) / abs($valeurVeille)) * 100, 1);

            if ($variation > $rapport['configuration']['seuilVariationStable']) {
                $tendance = 1;
            } elseif ($variation < -$rapport['configuration']['seuilVariationStable']) {
                $tendance = -1;
            } else {
                $tendance = 0;
            }
        }

        $rapport['historique'][$nom][$index]['valeur'] = $valeur;
        $rapport['historique'][$nom][$index]['variation'] = $variation;
        $rapport['historique'][$nom][$index]['tendance'] = $tendance;

        $total += $valeur;
        $minimum = $minimum === null ? $valeur : min($minimum, $valeur);
        $maximum = $maximum === null ? $valeur : max($maximum, $valeur);
        $valeurVeille = $valeur;
    }

    $nbJours = count($rapport['historique'][$nom]);
    $rapport['statistiques'][$nom] = array(
        'nb_jours' => $nbJours,
        'total' => round($total, 3),
        'moyenne' => $nbJours > 0 ? round($total / $nbJours, 3) : 0,
        'minimum' => $minimum === null ? 0 : $minimum,
        'maximum' => $maximum === null ? 0 : $maximum
    );
}

// TEMPERATURES ET MESSAGES JEEDOM

$tempInterieure = floatval($rapport['temperatures']['mychambre']);
$tempExterieure = floatval($rapport['temperatures']['portail']);
$ecart = round($tempInterieure - $tempExterieure, 1);

$rapport['comparaisons']['temperature'] = array(
    'interieur' => $tempInterieure,
    'exterieur' => $tempExterieure,
    'ecart' => $ecart
);

if (abs($ecart) >= $rapport['configuration']['seuilEcartTemperature']) {
    rapportAjouterAlerte($rapport, 'info', 'temperature', 'Écart intérieur / extérieur de '.$ecart.' °C');
}

$rapport['statistiques']['messages'] = array(
    'total' => count($rapport['messages']),
    'plugins' => array()
);

foreach ($rapport['messages'] as $message) {
    $plugin = $message['plugin'] == '' ? 'Inconnu' : $message['plugin'];

    if (!isset($rapport['statistiques']['messages']['plugins'][$plugin])) {
        $rapport['statistiques']['messages']['plugins'][$plugin] = 0;
    }

    $rapport['statistiques']['messages']['plugins'][$plugin]++;
}

// ALERTES CONSOMMATIONS ET RESUME

$libellesConsommation = array(
    'electricite' => 'Consommation électrique',
    'eau'         => 'Consommation d\'eau',
    'chauffage'   => 'Temps de chauffage'
);

foreach ($rapport['historique'] as $nom => $historique) {

    foreach ($historique as $jour) {

        if ($jour['variation'] === null) {
            continue;
        }

        $libelle = isset($libellesConsommation[$nom]) ? $libellesConsommation[$nom] : ucfirst($nom);

        if ($jour['variation'] >= $rapport['configuration']['seuilVariationImportante']) {

            rapportAjouterAlerte(
                $rapport,
                'warning',
                'consommation',
                $libelle.' en forte hausse le '.$jour['date'].' (+'.$jour['variation'].' %)',
                $nom
            );

        }

        if ($jour['variation'] <= -$rapport['configuration']['seuilVariationImportante']) {

            rapportAjouterAlerte(
                $rapport,
                'info',
                'consommation',
                $libelle.' en forte baisse le '.$jour['date'].' ('.$jour['variation'].' %)',
                $nom
            );

        }

    }

}

if ($rapport['statistiques']['messages']['total'] > 0) {

    rapportAjouterAlerte(
        $rapport,
        'info',
        'jeedom',
        $rapport['statistiques']['messages']['total'].' message(s) Jeedom'
    );

}

$priorite = array(
    'danger'  => 1,
    'warning' => 2,
    'info'    => 3
);

usort($rapport['alertes'], function($a, $b) use ($priorite) {
    return $priorite[$a['niveau']] <=> $priorite[$b['niveau']];
});

$nbAlertes = count($rapport['alertes']);

$rapport['resume'] = array(
    'nb_alertes' => $nbAlertes,
    'texte' => $nbAlertes === 0
        ? 'Aucune anomalie détectée.'
        : ($nbAlertes === 1 ? 'Une alerte nécessite votre attention.' : $nbAlertes.' alertes nécessitent votre attention.')
);

$scenario->setTags(array(
    'rapport' => json_encode($rapport, JSON_UNESCAPED_UNICODE)
));

// ----- BLOC 3 - VALIDATION DES DONNEES ET TRAITEMENTS -----

$rapport = json_decode($scenario->getTag('rapport'), true);

if (!is_array($rapport)) {
    $scenario->setLog("Impossible de récupérer le rapport.");
    return;
}

$scenario->setLog('');
$scenario->setLog('================================================');
$scenario->setLog('VALIDATION DU RAPPORT DOMOTIQUE');
$scenario->setLog('================================================');

$scenario->setLog('Période : '.$rapport['periode']['debut'].' -> '.$rapport['periode']['fin']);
$scenario->setLog('Soleil : '.$rapport['soleil']['lever'].' -> '.$rapport['soleil']['coucher'].' ('.$rapport['soleil']['duree'].')');

$scenario->setLog('');
$scenario->setLog('===== METEO =====');

foreach ($rapport['comparaisons']['meteo'] as $jour) {
    $variation = $jour['variation'] === null ? '-' : sprintf('%+.1f °C', $jour['variation']);
    $scenario->setLog($jour['jour'].' '.$jour['date'].' | moyenne '.$jour['moyenne'].' °C | '.$variation);
}

$scenario->setLog('');
$scenario->setLog('===== HISTORIQUES =====');

foreach ($rapport['historique'] as $nom => $historique) {
    $scenario->setLog(strtoupper($nom).' : '.$rapport['statistiques'][$nom]['nb_jours'].' jour(s), total '.$rapport['statistiques'][$nom]['total']);
}

$scenario->setLog('');
$scenario->setLog('===== TEMPERATURES =====');
$scenario->setLog('Intérieur : '.$rapport['comparaisons']['temperature']['interieur'].' °C');
$scenario->setLog('Extérieur : '.$rapport['comparaisons']['temperature']['exterieur'].' °C');
$scenario->setLog('Écart : '.$rapport['comparaisons']['temperature']['ecart'].' °C');

$scenario->setLog('');
$scenario->setLog('===== ALERTES =====');

if ($rapport['resume']['nb_alertes'] === 0) {
    $scenario->setLog('Aucune alerte.');
} else {
    foreach ($rapport['alertes'] as $alerte) {
        $scenario->setLog('['.strtoupper($alerte['niveau']).'] '.$alerte['message']);
    }
}

$scenario->setLog('');
$scenario->setLog('Résumé : '.$rapport['resume']['texte']);

// ----- BLOC 4 - GENERATION HTML ET ENVOI MAIL -----

if (!function_exists('rapportIconeMeteo')) {
    function rapportIconeMeteo($condition) {
        $condition = mb_strtolower($condition, 'UTF-8');

        if (strpos($condition, 'soleil') !== false || strpos($condition, 'ensole') !== false) {
            return '☀️';
        }

        if (strpos($condition, 'nuage') !== false) {
            return '⛅';
        }

        if (strpos($condition, 'couvert') !== false) {
            return '☁️';
        }

        if (strpos($condition, 'pluie') !== false) {
            return '🌧️';
        }

        if (strpos($condition, 'averse') !== false) {
            return '🌦️';
        }

        if (strpos($condition, 'orage') !== false) {
            return '⛈️';
        }

        if (strpos($condition, 'neige') !== false) {
            return '❄️';
        }

        if (strpos($condition, 'brouillard') !== false) {
            return '🌫️';
        }

        if (strpos($condition, 'vent') !== false) {
            return '💨';
        }

        return '🌤️';
    }
}

if (!function_exists('rapportCouleurTendanceConso')) {
    function rapportCouleurTendanceConso($tendance) {

        switch ($tendance) {

            case 1:
                return '#D32F2F';   // Hausse = rouge

            case -1:
                return '#2E7D32';   // Baisse = vert

            case 0:
                return '#757575';   // Stable = gris

            default:
                return '#757575';
        }

    }
}

if (!function_exists('rapportCouleurTendanceMeteo')) {
    function rapportCouleurTendanceMeteo($tendance) {

        switch ($tendance) {

            case 1:
                return '#D32F2F';   // Plus chaud

            case -1:
                return '#1976D2';   // Plus frais

            case 0:
                return '#757575';

            default:
                return '#757575';
        }

    }
}

if (!function_exists('rapportLibelleTendanceConso')) {
    function rapportLibelleTendanceConso($tendance) {

        switch ($tendance) {

            case 1:
                return '🔺 En hausse';

            case -1:
                return '🔻 En baisse';

            case 0:
                return '➖ Stable';

            default:
                return '-';
        }

    }
}

if (!function_exists('rapportLibelleTendanceMeteo')) {
    function rapportLibelleTendanceMeteo($tendance) {

        switch ($tendance) {

            case 1:
                return '↑ Plus chaud';

            case -1:
                return '↓ Plus frais';

            case 0:
                return '→ Stable';

            default:
                return '-';
        }

    }
}

if (!function_exists('rapportCellulesConsommation')) {

    function rapportCellulesConsommation($jour, $unite, $classe) {

        if ($jour === null) {

            return
                '<td class="'.$classe.' muted">-</td>'.
                '<td class="muted">-</td>'.
                '<td class="muted">-</td>';

        }

        $variation = $jour['variation'] === null ? '-' : sprintf('%+.1f %%', $jour['variation']);

$couleur = rapportCouleurTendanceConso($jour['tendance']);

        $decimales = $unite === 'kWh' ? 3 : 0;

        return
            '<td class="'.$classe.'"><b>'.number_format($jour['valeur'], $decimales, ',', ' ').' '.$unite.'</b></td>'.
            '<td><span style="color:'.$couleur.';">'.$variation.'</span></td>'.
            '<td><span style="color:'.$couleur.';font-weight:bold;">'.rapportLibelleTendanceConso($jour['tendance']).'</span></td>';

    }

}

if (!function_exists('rapportTableauConsommations')) {
    function rapportTableauConsommations($rapport) {
        $unites = array(
            'electricite' => 'kWh',
            'eau' => 'L',
            'chauffage' => 'h'
        );

        $dates = array();
        $index = array();

        foreach ($unites as $nom => $unite) {
            $index[$nom] = array();

            foreach ($rapport['historique'][$nom] as $jour) {
                $dates[$jour['date']] = strtotime(str_replace('/', '-', $jour['date']));
                $index[$nom][$jour['date']] = $jour;
            }
        }

        uasort($dates, function($a, $b) {
            return $a <=> $b;
        });

        $html = '<div class="section"><h2>Consommations</h2><table class="consoTable">';
$html .= '<tr>

<th rowspan="2" class="dateCol">Date</th>

<th colspan="3" class="groupTitle colElec">⚡ Électricité</th>

<th colspan="3" class="groupTitle colEau">💧 Eau</th>

<th colspan="3" class="groupTitle colChauffage">🔥 Chauffage</th>

</tr>';

$html .= '<tr>

<th class="colElec">Conso</th>
<th>%</th>
<th>Tendance</th>

<th class="colEau">Conso</th>
<th>%</th>
<th>Tendance</th>

<th class="colChauffage">Conso</th>
<th>%</th>
<th>Tendance</th>

</tr>';
        foreach (array_keys($dates) as $date) {
            $html .= '<tr>';
            $html .= '<td class="dateCol"><b>'.$date.'</b></td>';
$html .= rapportCellulesConsommation(
    isset($index['electricite'][$date]) ? $index['electricite'][$date] : null,
    $unites['electricite'],
    'colElec'
);

$html .= rapportCellulesConsommation(
    isset($index['eau'][$date]) ? $index['eau'][$date] : null,
    $unites['eau'],
    'colEau'
);

$html .= rapportCellulesConsommation(
    isset($index['chauffage'][$date]) ? $index['chauffage'][$date] : null,
    $unites['chauffage'],
    'colChauffage'
);            
$html .= '</tr>';
        }

        $html .= '</table></div>';

        return $html;
    }
}

$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Domotique</title>
<style>

body{
margin:0;
padding:10px;
background:#F8F7FA;
font-family:Arial,Helvetica,sans-serif;
color:#2E2A36;
}

.container{
max-width:900px;
margin:auto;
background:#FFFFFF;
border-radius:10px;
overflow:hidden;
box-shadow:0 6px 18px rgba(0,0,0,.10);
}

.header{
background:#5B3B8A;
color:#FFFFFF;
padding:18px 24px;
}

.header h1{
margin:0;
font-size:30px;
font-weight:600;
}

.section{
padding:12px 18px;
}

.section h2{
margin:0 0 8px 0;
color:#5B3B8A;
border-bottom:2px solid #C9B7F0;
padding-bottom:4px;
font-size:20px;
}

.resume{
background:#F5F0FC;
border-left:5px solid #7A5CC7;
padding:10px;
margin-bottom:10px;
border-radius:6px;
}

table{
width:100%;
border-collapse:collapse;
margin-top:4px;
margin-bottom:8px;
}

th{
background:#7A5CC7;
color:#FFFFFF;
padding:8px;
font-weight:600;
text-align:left;
}

td{
padding:6px 7px;
border-bottom:1px solid #E8E3F1;
}

tr:nth-child(even){
background:#FCFBFE;
}

/****************************************************/
/* TABLEAU CONSOMMATIONS */
/****************************************************/

.consoTable th,
.consoTable td{
font-size:12px;
line-height:1.25;
padding:6px 5px;
text-align:center;
vertical-align:middle;
}

.consoTable td+td,
.consoTable th+th{
border-left:1px solid #D8D5E5;
}

.consoTable .dateCol{
width:95px;
text-align:left;
white-space:nowrap;
font-weight:bold;
}

.consoTable .groupTitle{
text-align:center;
font-size:13px;
}

.consoTable .colElec{
border-left:3px solid #43315F !important;
}

.consoTable .colEau{
border-left:3px solid #43315F !important;
}

.consoTable .colChauffage{
border-left:3px solid #43315F !important;
}


.muted{
color:#8A8593;
}

.footer{
background:#F5F0FC;
padding:12px;
text-align:center;
font-size:12px;
color:#756B84;
border-top:1px solid #DDD6EC;
}

</style>
</head>

<body>

<div class="container">
';

$html .= '

<div class="header">

<table style="width:100%;border:none;color:white;margin:0;">

<tr>

<td style="border:none;text-align:left;vertical-align:top;">

<h1 style="margin:0;">🏠 Rapport Domotique</h1>

<div style="margin-top:12px;font-size:14px;line-height:22px;">

📅 Généré le '.date('d/m/Y à H:i').'<br>

🗓️ Période : '.date('d/m/Y',strtotime($rapport['periode']['debut'])).' → '.date('d/m/Y',strtotime($rapport['periode']['fin'])).'

</div>

</td>

<td style="border:none;text-align:right;vertical-align:top;white-space:nowrap;font-size:15px;line-height:28px;">

🌅 Lever : '.$rapport['soleil']['lever'].'<br>

🌇 Coucher : '.$rapport['soleil']['coucher'].'<br>

☀️ Jour : '.$rapport['soleil']['duree'].'

</td>

</tr>

</table>

</div>

';

$html .= '<div class="section"><h2>⚠️ Alertes</h2>';

$alertesVisibles = array();

foreach ($rapport['alertes'] as $alerte) {
    if ($alerte['niveau'] !== 'info') {
        $alertesVisibles[] = $alerte;
    }
}

if (count($alertesVisibles) === 0) {
    $html .= '<div class="resume">Aucune alerte détectée.</div>';
} else {
    $html .= '<table><tr><th>Niveau</th><th>Message</th></tr>';

    foreach ($alertesVisibles as $alerte) {
        if ($alerte['niveau'] === 'danger') {
            $niveau = '<div style="background:#FDECEA;color:#B71C1C;padding:4px 8px;border-radius:6px;font-weight:bold;text-align:center;">🔴 Critique</div>';
        } elseif ($alerte['niveau'] === 'warning') {
            $niveau = '<div style="background:#FFF8E1;color:#F57F17;padding:4px 8px;border-radius:6px;font-weight:bold;text-align:center;">🟠 Attention</div>';
        }

        $html .= '<tr><td>'.$niveau.'</td><td>'.$alerte['message'].'</td></tr>';
    }

    $html .= '</table>';
}

$html .= '</div>';

$html .= '

<div class="section">

<h2>🌤️ Prévisions météo</h2>

<table>

<tr>

<th style="width:120px;">Jour</th>

<th style="width:100px;">Date</th>

<th>Prévision</th>

<th style="width:90px;">Évolution</th>

<th style="width:130px;">Tendance</th>

</tr>

';
foreach ($rapport['comparaisons']['meteo'] as $jour) {
    foreach ($rapport['meteo'] as $meteo) {
        if ($meteo['date'] !== $jour['date']) {
            continue;
        }

        $couleur = rapportCouleurTendanceMeteo($jour['tendance']);
        $variation = $jour['variation'] === null ? '-' : sprintf('%+.1f °C', $jour['variation']);

$html .= '<tr>';

$html .= '<td><b>'.$meteo['jour'].'</b></td>';

$html .= '<td>'.$meteo['date'].'</td>';

$html .= '<td>'

.rapportIconeMeteo($meteo['condition'])

.' '

.$meteo['condition']

.' ('

.$meteo['temp_min']

.' → '

.$meteo['temp_max']

.' °C)'

.'</td>';

$html .= '<td>

<span style="color:'.$couleur.';font-weight:bold;">

'.$variation.'

</span>

</td>';

$html .= '<td>

<span style="color:'.$couleur.';font-weight:bold;">

'.rapportLibelleTendanceMeteo($jour['tendance']).'

</span>

</td>';

$html .= '</tr>';
        break;
    }
}

$html .= '</table></div>';


$html .= rapportTableauConsommations($rapport);

$html .= '<div class="section"><h2>🌡️ Températures</h2><table><tr><th>Capteur</th><th>Valeur</th></tr>';
$html .= '<tr><td>Extérieur</td><td>'.$rapport['temperatures']['portail'].' °C</td></tr>';
$html .= '<tr><td>Cabanon</td><td>'.$rapport['temperatures']['cabanon'].' °C</td></tr>';
$html .= '<tr><td>Ma chambre</td><td>'.$rapport['temperatures']['mychambre'].' °C</td></tr>';
$html .= '<tr><td>Salle de bain</td><td>'.$rapport['temperatures']['sdbhaut'].' °C</td></tr>';
$html .= '<tr><td><b>Écart intérieur / extérieur</b></td><td><b>'.$rapport['comparaisons']['temperature']['ecart'].' °C</b></td></tr>';
$html .= '</table></div>';

$html .= '<div class="section"><h2>📨 Messages Jeedom</h2><table><tr><th>Plugin</th><th>Nombre</th></tr>';

foreach ($rapport['statistiques']['messages']['plugins'] as $plugin => $nombre) {
    $html .= '<tr><td>'.$plugin.'</td><td>'.$nombre.'</td></tr>';
}

$html .= '<tr><td><b>Total</b></td><td><b>'.$rapport['statistiques']['messages']['total'].'</b></td></tr></table></div>';
$html .= '<div class="footer">Rapport généré automatiquement par Jeedom<br>'.date('d/m/Y H:i:s').'</div>';
$html .= '</div></body></html>';

// MINIFICATION HTML, ENVOI ET DEBUG

$html = preg_replace('/>\s+</', '><', $html);
$html = str_replace(array("\r", "\n", "\t"), '', $html);
$html = preg_replace('/ {2,}/', ' ', $html);
$html = trim($html);

$message = array(
    'title' => 'Rapport Domotique',
    'message' => $html
);

//Envoi Mail Moi

$cmdMail = cmd::byString('#[Connexions][SMTP Gmail][Mail to Radiogrange gmail]#');

if (is_object($cmdMail)) {
    $cmdMail->execCmd($message);
    $scenario->setLog('Mail envoyé avec succès');
} else {
    rapportAjouterAlerte($rapport, 'danger', 'configuration', 'Commande SMTP introuvable');
    $scenario->setLog('Commande SMTP introuvable : mail non envoyé');
}

//Envoi Mail Emma

$cmdMail = cmd::byString('#[Connexions][SMTP Gmail][Mail to Emma gmail]#');

if (is_object($cmdMail)) {
    $cmdMail->execCmd($message);
    $scenario->setLog('Mail envoyé avec succès');
} else {
    rapportAjouterAlerte($rapport, 'danger', 'configuration', 'Commande SMTP introuvable');
    $scenario->setLog('Commande SMTP introuvable : mail non envoyé');
}


$scenario->setTags(array(
    'rapport' => json_encode($rapport, JSON_UNESCAPED_UNICODE),
    'rapport_html' => $html
));

$scenario->setLog('Taille HTML : '.strlen($html).' octets');
$scenario->setLog('Alertes : '.count($rapport['alertes']));
$scenario->setLog('Messages Jeedom : '.$rapport['statistiques']['messages']['total']);