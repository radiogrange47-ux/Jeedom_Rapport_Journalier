$rapport = array();
$rapport['historiqueSemainePrecedente'] = array('electricite' => 0, 'eau' => 0, 'chauffage' => 0);

$config = array(
    'nbJoursHistorique' => 7,
    // Seuil d'alerte : 1.2 = 120 % de la moyenne des 31 jours précédents
    'seuilMoyenneConso' => 1.2,
    'seuils' => array(
        'gel' => 0,
        'froid' => 5,
        'forteChaleur' => 35,
        'canicule' => 39,
        'variationStable' => 2,
        'variationImportante' => 20,
        'variationMeteo' => 0.5,
        'ecartTemperature' => 8
    )
);

if (!function_exists('rapportCmdValue')) {
    function rapportCmdValue($commande, $defaut = null) {
        $cmd = cmd::byString($commande);
        if (!is_object($cmd)) {
            return $defaut;
        }
        $valeur = $cmd->execCmd();
        return ($valeur === null || $valeur === '') ? $defaut : $valeur;
    }
}

if (!function_exists('rapportFloat')) {
    function rapportFloat($valeur, $defaut = 0) {
        if ($valeur === null || $valeur === '') {
            return floatval($defaut);
        }
        return floatval(str_replace(',', '.', $valeur));
    }
}

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

if (!function_exists('rapportDetermineNiveau')) {
    function rapportDetermineNiveau($score) {
        if ($score >= 3) return 'danger';
        if ($score >= 1) return 'warning';
        return 'info';
    }
}

if (!function_exists('rapportFormatVariation')) {
    function rapportFormatVariation($valeur) {
        if ($valeur === null) return '-';
        return sprintf('%+.1f', floatval($valeur));
    }
}

if (!function_exists('rapportFormatDateAvecJour')) {
    function rapportFormatDateAvecJour($dateStr) {
        $jours = array('Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim');
        $timestamp = strtotime(str_replace('/', '-', $dateStr));
        if ($timestamp === false) return $dateStr;
        $numJour = (int)date('w', $timestamp);
        if ($numJour == 0) $numJour = 6;
        else $numJour--;
        return $jours[$numJour].' '.$dateStr;
    }
}

if (!function_exists('rapportEvaluerRegles')) {
    function rapportEvaluerRegles($contexte, $regles) {
        $resultats = array();
        foreach ($regles as $regle) {
            if (isset($regle['condition']) && $regle['condition']($contexte)) {
                $resultats[] = array(
                    'niveau' => $regle['niveau'],
                    'message' => $regle['message']($contexte),
                    'score' => $regle['score']
                );
            }
        }
        return $resultats;
    }
}

if (!function_exists('rapportIconeMeteo')) {
    function rapportIconeMeteo($condition) {
        $condition = mb_strtolower($condition, 'UTF-8');
        if (strpos($condition, 'soleil') !== false || strpos($condition, 'ensole') !== false) return '☀️';
        if (strpos($condition, 'nuage') !== false) return '⛅';
        if (strpos($condition, 'couvert') !== false) return '☁️';
        if (strpos($condition, 'pluie') !== false) return '🌧️';
        if (strpos($condition, 'averse') !== false) return '🌦️';
        if (strpos($condition, 'orage') !== false) return '⛈️';
        if (strpos($condition, 'neige') !== false) return '❄️';
        if (strpos($condition, 'brouillard') !== false) return '🌫️';
        if (strpos($condition, 'vent') !== false) return '💨';
        return '🌤️';
    }
}

if (!function_exists('rapportCouleurTendanceConso')) {
    function rapportCouleurTendanceConso($tendance) {
        switch ($tendance) {
            case 1: return '#D32F2F';
            case -1: return '#2E7D32';
            case 0: return '#757575';
            default: return '#757575';
        }
    }
}

if (!function_exists('rapportCouleurTendanceMeteo')) {
    function rapportCouleurTendanceMeteo($tendance) {
        switch ($tendance) {
            case 1: return '#D32F2F';
            case -1: return '#1976D2';
            case 0: return '#757575';
            default: return '#757575';
        }
    }
}

if (!function_exists('rapportLibelleTendanceConso')) {
    function rapportLibelleTendanceConso($tendance) {
        switch ($tendance) {
            case 1: return '🔺 En hausse';
            case -1: return '🔻 En baisse';
            case 0: return '➖ Stable';
            default: return '-';
        }
    }
}

if (!function_exists('rapportLibelleTendanceMeteo')) {
    function rapportLibelleTendanceMeteo($tendance) {
        switch ($tendance) {
            case 1: return '↑ Plus chaud';
            case -1: return '↓ Plus frais';
            case 0: return '→ Stable';
            default: return '-';
        }
    }
}

if (!function_exists('rapportTableauConsommations')) {
    function rapportTableauConsommations($rapport) {
        $jours = array('Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim');
        $index = array('electricite' => array(), 'eau' => array(), 'chauffage' => array());
        
        foreach (array('electricite', 'eau', 'chauffage') as $nom) {
            foreach ($rapport['historique'][$nom] as $jour) {
                $timestamp = strtotime(str_replace('/', '-', $jour['date']));
                $numJour = (int)date('w', $timestamp);
                if ($numJour == 0) $numJour = 6;
                else $numJour--;
                $index[$nom][$numJour] = $jour;
            }
        }

        $html = '<div class="section"><h2>Consommations</h2><table class="consoTable"><colgroup><col style="width:12%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"></colgroup>';
        $html .= '<tr><th rowspan="2" class="dateCol">Jour</th>';
        $html .= '<th colspan="3" class="groupTitle colElec">⚡ Électricité</th>';
        $html .= '<th colspan="3" class="groupTitle colEau">💧 Eau</th>';
        $html .= '<th colspan="2" class="groupTitle colChauffage">🔥 Chauffage</th></tr>';
        $html .= '<tr><th class="colElec">Conso</th><th>%</th><th>Coût</th>';
        $html .= '<th class="colEau">Conso</th><th>%</th><th>Coût</th>';
        $html .= '<th class="colChauffage">Conso</th><th>%</th></tr>';

        $html .= '<tr style="background:#F5F0FC;font-size:10px;font-weight:normal;">';
        $html .= '<td class="dateCol">Ref moy/j</td>';
        
        foreach (array('electricite', 'eau', 'chauffage') as $nom) {
            $moyenne = $rapport['moyennes31Jours'][$nom];
            if ($nom === 'chauffage') {
                $total = number_format($moyenne, 1, ',', ' ');
                $html .= '<td class="col'.$nom.'">'.$total.' h</td>';
                $html .= '<td>-</td>';
            } else {
                $decimales = $nom === 'electricite' ? 3 : 0;
                $total = number_format($moyenne, $decimales, ',', ' ');
                $html .= '<td class="col'.$nom.'">'.$total.($nom === 'electricite' ? ' kWh' : ' L').'</td>';
                $html .= '<td>-</td>';
                
                if ($nom === 'electricite') {
                    $coutMoyen = 0.5211 + ($moyenne * 0.2001);
                } else {
                    $coutMoyen = ($moyenne / 1000) * 3.67;
                }
                $html .= '<td>'.number_format($coutMoyen, 2, ',', ' ').' €</td>';
            }
        }
        
        $html .= '</tr>';

        $totaux = array('electricite' => 0, 'eau' => 0, 'chauffage' => 0);

        $debutSemaine = strtotime($rapport['periode']['debut']);
        $finPeriode = strtotime($rapport['periode']['fin']);
        for ($i = 0; $i < 7; $i++) {
            $dateJour = strtotime('+'.$i.' days', $debutSemaine);
            $html .= '<tr>';
            $dateStyle = $dateJour > $finPeriode ? ' style="color:#8A8593;"' : '';
            $html .= '<td class="dateCol"'.$dateStyle.'><b>'.$jours[$i].'</b><br>'.date('d/m/Y', $dateJour).'</td>';

            foreach (array('electricite', 'eau', 'chauffage') as $nom) {
                $jour = isset($index[$nom][$i]) ? $index[$nom][$i] : null;
                if ($jour === null) {
                    if ($nom === 'chauffage') {
                        $html .= '<td class="col'.$nom.' muted">-</td><td class="muted">-</td>';
                    } else {
                        $html .= '<td class="col'.$nom.' muted">-</td><td class="muted">-</td><td class="muted">-</td>';
                    }
                    continue;
                }

                $decimales = $nom === 'electricite' ? 3 : 0;
                $valeur = number_format($jour['valeur'], $decimales, ',', ' ');
                $variation = $jour['variation'] === null ? '-' : sprintf('%+.1f %%', $jour['variation']);
                $couleur = rapportCouleurTendanceConso($jour['tendance']);
                $totaux[$nom] += floatval($jour['valeur']);

                if ($nom === 'electricite') {
                    $kwhParJour = floatval($jour['valeur']);
                    $cout = 0.5211 + ($kwhParJour * 0.2001);
                    $coutFormate = number_format($cout, 2, ',', ' ').' €';
                    $html .= '<td class="col'.$nom.'"><b>'.$valeur.' kWh</b></td>';
                    $html .= '<td><span style="color:'.$couleur.';">'.$variation.'</span></td>';
                    $html .= '<td><span style="font-weight:bold;">'.$coutFormate.'</span></td>';
                } elseif ($nom === 'eau') {
                    $litresParJour = floatval($jour['valeur']);
                    $m3ParJour = $litresParJour / 1000;
                    $cout = $m3ParJour * 3.67;
                    $coutFormate = number_format($cout, 2, ',', ' ').' €';
                    $html .= '<td class="col'.$nom.'"><b>'.$valeur.' L</b></td>';
                    $html .= '<td><span style="color:'.$couleur.';">'.$variation.'</span></td>';
                    $html .= '<td><span style="font-weight:bold;">'.$coutFormate.'</span></td>';
                } else {
                    $html .= '<td class="col'.$nom.'"><b>'.$valeur.' h</b></td>';
                    $html .= '<td><span style="color:'.$couleur.';">'.$variation.'</span></td>';
                }
            }

            $html .= '</tr>';
        }

        $html .= '<tr style="background:#E8E3F1;font-weight:bold;">';
        $html .= '<td class="dateCol"><b>Total</b></td>';

        foreach (array('electricite', 'eau', 'chauffage') as $nom) {
            if ($nom === 'chauffage') {
                $decimales = 1;
                $total = number_format($totaux[$nom], $decimales, ',', ' ');
                $html .= '<td class="col'.$nom.'" style="font-weight:bold;">'.$total.' h</td>';
                $html .= '<td>-</td>';
            } else {
                $decimales = $nom === 'electricite' ? 3 : 0;
                $total = number_format($totaux[$nom], $decimales, ',', ' ');
                $html .= '<td class="col'.$nom.'" style="font-weight:bold;">'.$total.($nom === 'electricite' ? ' kWh' : ' L').'</td>';
                $html .= '<td>-</td>';
                
                if ($nom === 'electricite') {
                    $coutTotal = 0.5211 + ($totaux[$nom] * 0.2001);
                    $coutFormate = number_format($coutTotal, 2, ',', ' ').' €';
                } else {
                    $coutTotal = ($totaux[$nom] / 1000) * 3.67;
                    $coutFormate = number_format($coutTotal, 2, ',', ' ').' €';
                }
                $html .= '<td style="font-weight:bold;">'.$coutFormate.'</td>';
            }
        }

        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<table class="consoTable consoPrevious"><colgroup><col style="width:12%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"><col style="width:11%;"></colgroup>';
        $html .= '<tr style="background:#F5F0FC;font-size:10px;font-weight:normal;border-top:2px solid #7A5CC7;">';
        $html .= '<td class="dateCol">Semaine<br>précédente</td>';
        
        foreach (array('electricite', 'eau', 'chauffage') as $nom) {
            if ($nom === 'chauffage') {
                $total = number_format($rapport['historiqueSemainePrecedente'][$nom], 1, ',', ' ');
                $html .= '<td class="col'.$nom.'">'.$total.' h</td>';
                $html .= '<td>-</td>';
            } else {
                $decimales = $nom === 'electricite' ? 3 : 0;
                $total = number_format($rapport['historiqueSemainePrecedente'][$nom], $decimales, ',', ' ');
                $html .= '<td class="col'.$nom.'">'.$total.($nom === 'electricite' ? ' kWh' : ' L').'</td>';
                $html .= '<td>-</td>';
                
                if ($nom === 'electricite') {
                    $coutTotal = 0.5211 + ($rapport['historiqueSemainePrecedente'][$nom] * 0.2001);
                    $coutFormate = number_format($coutTotal, 2, ',', ' ').' €';
                } else {
                    $coutTotal = ($rapport['historiqueSemainePrecedente'][$nom] / 1000) * 3.67;
                    $coutFormate = number_format($coutTotal, 2, ',', ' ').' €';
                }
                $html .= '<td>'.$coutFormate.'</td>';
            }
        }
        
        $html .= '</tr>';
        $html .= '</table></div>';
        return $html;
    }
}

if (!function_exists('rapportBuildHtml')) {
    function rapportBuildHtml($rapport) {
        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Rapport Domotique</title>';
        $html .= '<style>';
        $html .= 'body{margin:0;padding:10px;background:#F8F7FA;font-family:Arial,Helvetica,sans-serif;color:#2E2A36;}';
        $html .= '.container{max-width:900px;margin:auto;background:#FFFFFF;border-radius:10px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.10);}';
        $html .= '.header{background:#5B3B8A;color:#FFFFFF;padding:18px 24px;}';
        $html .= '.header h1{margin:0;font-size:30px;font-weight:600;}';
        $html .= '.section{padding:12px 18px;}';
        $html .= '.section h2{margin:0 0 8px 0;color:#5B3B8A;border-bottom:2px solid #C9B7F0;padding-bottom:4px;font-size:20px;}';
        $html .= '.resume{background:#F5F0FC;border-left:5px solid #7A5CC7;padding:10px;margin-bottom:10px;border-radius:6px;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-top:4px;margin-bottom:8px;}';
        $html .= 'th{background:#7A5CC7;color:#FFFFFF;padding:8px;font-weight:600;text-align:left;}';
        $html .= 'td{padding:6px 7px;border-bottom:1px solid #E8E3F1;}';
        $html .= 'tr:nth-child(even){background:#FCFBFE;}';
        $html .= '.consoTable{table-layout:fixed;}';
        $html .= '.consoTable th,.consoTable td{font-size:12px;line-height:1.25;padding:6px 5px;text-align:center;vertical-align:middle;overflow-wrap:break-word;}';
        $html .= '.consoTable td+td,.consoTable th+th{border-left:1px solid #D8D5E5;}';
        $html .= '.consoTable th:nth-child(4),.consoTable td:nth-child(4){border-right:3px solid #43315F !important;}';
        $html .= '.consoTable th:nth-child(7),.consoTable td:nth-child(7){border-right:3px solid #43315F !important;}';
        $html .= '.consoTable .dateCol{width:95px;text-align:left;white-space:nowrap;font-weight:bold;}';
        $html .= '.consoTable .groupTitle{text-align:center;font-size:13px;}';
        $html .= '.consoTable .colElec{border-left:3px solid #43315F !important;}';
        $html .= '.consoTable .colEau{border-left:3px solid #43315F !important;}';
        $html .= '.consoTable .colChauffage{border-left:3px solid #43315F !important;}';
        $html .= '.muted{color:#8A8593;}';
        $html .= '.footer{background:#F5F0FC;padding:12px;text-align:center;font-size:12px;color:#756B84;border-top:1px solid #DDD6EC;}';
        $html .= '</style></head><body><div class="container">';

        $html .= '<div class="header"><table style="width:100%;border:none;color:white;margin:0;"><tr>';
        $html .= '<td style="border:none;text-align:left;vertical-align:top;">';
        $html .= '<h1 style="margin:0;">🏠 Rapport Domotique</h1>';
        $html .= '<div style="margin-top:12px;font-size:14px;line-height:22px;">📅 Généré le '.date('d/m/Y à H:i').'<br>🗓️ Période : '.rapportFormatDateAvecJour(date('d/m/Y', strtotime($rapport['periode']['debut']))).' → '.rapportFormatDateAvecJour(date('d/m/Y', strtotime($rapport['periode']['fin']))).'</div>';
        $html .= '</td>';
        $html .= '<td style="border:none;text-align:right;vertical-align:top;white-space:nowrap;font-size:15px;line-height:28px;">🌅 Lever : '.$rapport['soleil']['lever'].'<br>🌇 Coucher : '.$rapport['soleil']['coucher'].'<br>☀️ Jour : '.$rapport['soleil']['duree'].'</td>';
        $html .= '</tr></table></div>';

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
                } else {
                    $niveau = '<div style="background:#E3F2FD;color:#1565C0;padding:4px 8px;border-radius:6px;font-weight:bold;text-align:center;">ℹ️ Info</div>';
                }
                $html .= '<tr><td>'.$niveau.'</td><td>'.$alerte['message'].'</td></tr>';
            }
            $html .= '</table>';
        }
        $html .= '</div>';

        $html .= '<div class="section"><h2>🌤️ Prévisions météo</h2><table><tr><th style="width:120px;">Jour</th><th style="width:100px;">Date</th><th>Prévision</th><th style="width:120px;">Températures</th><th style="width:90px;">Évolution</th></tr>';
        foreach ($rapport['comparaisons']['meteo'] as $jour) {
            foreach ($rapport['meteo'] as $meteo) {
                if ($meteo['date'] !== $jour['date']) {
                    continue;
                }
                $couleur = rapportCouleurTendanceMeteo($jour['tendance']);
                $variation = $jour['variation'] === null ? '-' : sprintf('%+.1f °C', $jour['variation']);
                $html .= '<tr><td><b>'.$meteo['jour'].'</b></td><td>'.$meteo['date'].'</td><td>'.rapportIconeMeteo($meteo['condition']).' '.$meteo['condition'].'</td><td><b>'.$meteo['temp_min'].' → '.$meteo['temp_max'].' °C</b></td><td><span style="color:'.$couleur.';font-weight:bold;">'.$variation.'</span></td></tr>';
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

        $html = preg_replace('/>\s+</', '><', $html);
        $html = str_replace(array("\r", "\n", "\t"), '', $html);
        $html = preg_replace('/ {2,}/', ' ', $html);
        return trim($html);
    }
}

$rapport['configuration'] = $config;
$debutSemaine = strtotime('monday this week');
$finHier = strtotime('yesterday');
$rapport['periode'] = array(
    'generation' => date('Y-m-d H:i:s'),
    'debut' => date('Y-m-d 00:00:00', $debutSemaine),
    'fin' => date('Y-m-d 23:59:59', $finHier)
);

$rapport['soleil'] = array(
    'lever' => rapportCmdValue('#[SalonCuisine][Commandes Volets][Lever Soleil]#', ''),
    'coucher' => rapportCmdValue('#[SalonCuisine][Commandes Volets][Coucher Soleil]#', '')
);

$lever = is_string($rapport['soleil']['lever']) ? str_replace('h', ':', $rapport['soleil']['lever']) : '';
$coucher = is_string($rapport['soleil']['coucher']) ? str_replace('h', ':', $rapport['soleil']['coucher']) : '';
$heureLever = strtotime($lever);
$heureCoucher = strtotime($coucher);

if ($heureLever !== false && $heureCoucher !== false && $heureCoucher > $heureLever) {
    $duree = $heureCoucher - $heureLever;
    $rapport['soleil']['duree'] = sprintf('%02dh%02d', floor($duree / 3600), floor(($duree % 3600) / 60));
} else {
    $rapport['soleil']['duree'] = 'Inconnue';
}

$rapport['historique'] = array();
$rapport['historique31Jours'] = array();
$rapport['moyennes31Jours'] = array();
$historiqueIds = array(
    'electricite' => 84,
    'eau' => 4204,
    'chauffage' => 639
);

$debutSemainePrecedente = date('Y-m-d 00:00:00', strtotime('-7 days', $debutSemaine));
$finSemainePrecedente = date('Y-m-d 23:59:59', strtotime('-1 day', $debutSemaine));

foreach ($historiqueIds as $nom => $idCmd) {
    $cmd = cmd::byId($idCmd);
    $rapport['historique'][$nom] = array();
    $rapport['historique31Jours'][$nom] = array();
    $rapport['historiqueSemainePrecedente'][$nom] = 0;

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
    
    $historySemainePrecedente = $cmd->getHistory($debutSemainePrecedente, $finSemainePrecedente);
    foreach ($historySemainePrecedente as $h) {
        $rapport['historiqueSemainePrecedente'][$nom] += floatval($h->getValue());
    }

    $debutHistorique31Jours = date('Y-m-d 00:00:00', strtotime('-31 days', $debutSemaine));
    $history31Jours = $cmd->getHistory($debutHistorique31Jours, $rapport['periode']['fin']);
    foreach ($history31Jours as $h) {
        $date = date('d/m/Y', strtotime($h->getDatetime()));
        if (!isset($rapport['historique31Jours'][$nom][$date])) {
            $rapport['historique31Jours'][$nom][$date] = 0;
        }
        $rapport['historique31Jours'][$nom][$date] += floatval($h->getValue());
    }
}

foreach ($historiqueIds as $nom => $idCmd) {
    $total31Jours = 0;
    $nombreJours31 = 0;
    for ($i = 1; $i <= 31; $i++) {
        $dateMoyenne = date('d/m/Y', strtotime('-'.$i.' days', $finHier));
        if (isset($rapport['historique31Jours'][$nom][$dateMoyenne])) {
            $total31Jours += $rapport['historique31Jours'][$nom][$dateMoyenne];
            $nombreJours31++;
        }
    }
    $rapport['moyennes31Jours'][$nom] = $nombreJours31 > 0 ? $total31Jours / $nombreJours31 : 0;
}

$rapport['meteo'] = array();
for ($i = 0; $i <= 4; $i++) {
    $suffixe = ($i === 0) ? '' : ' +'.$i;
    $cle = ($i === 0) ? 'aujourdhui' : 'j'.$i;
    $timestamp = strtotime('+'.$i.' day');

    $rapport['meteo'][$cle] = array(
        'jour' => ($i === 0) ? "Aujourd'hui" : rapportNomJour($timestamp),
        'date' => date('d/m/Y', $timestamp),
        'temp_min' => rapportFloat(rapportCmdValue('#[Extérieur][Gagny][Température Min'.$suffixe.']#', 0), 0),
        'temp_max' => rapportFloat(rapportCmdValue('#[Extérieur][Gagny][Température Max'.$suffixe.']#', 0), 0),
        'condition' => rapportCmdValue('#[Extérieur][Gagny][Condition'.$suffixe.']#', '')
    );
}

$rapport['temperatures'] = array(
    'portail' => rapportFloat(rapportCmdValue('#[Garage et Exterieur][ESP_Commande Portail][Temperature]#', 0), 0),
    'cabanon' => rapportFloat(rapportCmdValue('#[Gestions Plantes][ESP_Cuve A Pluie][Temperature]#', 0), 0),
    'mychambre' => rapportFloat(rapportCmdValue('#[Equipements ZigBee][Sonde_d5:d2:1c (MyChambre)][Température]#', 0), 0),
    'sdbhaut' => rapportFloat(rapportCmdValue('#[Equipements ZigBee][Sonde_d5:9d:74 (SdBHaut)][Température]#', 0), 0)
);

$rapport['messages'] = array();
foreach (message::all() as $message) {
    $rapport['messages'][] = array(
        'date' => $message->getDate(),
        'plugin' => $message->getPlugin(),
        'action' => $message->getAction(),
        'message' => $message->getMessage()
    );
}

$rapport['comparaisons'] = array();
$rapport['statistiques'] = array();

$previousMoyenne = null;
$rapport['comparaisons']['meteo'] = array();
foreach ($rapport['meteo'] as $jour) {
    $moyenne = round((floatval($jour['temp_min']) + floatval($jour['temp_max'])) / 2, 1);
    $variation = null;
    $tendance = null;

    if ($previousMoyenne !== null) {
        $variation = round($moyenne - $previousMoyenne, 1);
        if ($variation > $config['seuils']['variationMeteo']) {
            $tendance = 1;
        } elseif ($variation < -$config['seuils']['variationMeteo']) {
            $tendance = -1;
        } else {
            $tendance = 0;
        }
    }

    $rapport['comparaisons']['meteo'][] = array(
        'jour' => $jour['jour'],
        'date' => $jour['date'],
        'moyenne' => $moyenne,
        'variation' => $variation,
        'tendance' => $tendance
    );

    $previousMoyenne = $moyenne;
}

foreach ($rapport['historique'] as $nom => $historique) {
    $valeurPrecedente = null;
    $total = 0;
    $minimum = null;
    $maximum = null;

    foreach ($historique as $index => $jour) {
        $valeur = round(floatval($jour['valeur']), 3);
        $variation = null;
        $tendance = null;

        if ($valeurPrecedente !== null && $valeurPrecedente != 0) {
            $variation = round((($valeur - $valeurPrecedente) / abs($valeurPrecedente)) * 100, 1);
            if ($variation > $config['seuils']['variationStable']) {
                $tendance = 1;
            } elseif ($variation < -$config['seuils']['variationStable']) {
                $tendance = -1;
            } else {
                $tendance = 0;
            }
        }

        $rapport['historique'][$nom][$index]['valeur'] = $valeur;
        $rapport['historique'][$nom][$index]['variation'] = $variation;
        $rapport['historique'][$nom][$index]['tendance'] = $tendance;

        $total += $valeur;
        $minimum = ($minimum === null) ? $valeur : min($minimum, $valeur);
        $maximum = ($maximum === null) ? $valeur : max($maximum, $valeur);
        $valeurPrecedente = $valeur;
    }

    $rapport['statistiques'][$nom] = array(
        'nb_jours' => count($rapport['historique'][$nom]),
        'total' => round($total, 3),
        'moyenne' => count($rapport['historique'][$nom]) > 0 ? round($total / count($rapport['historique'][$nom]), 3) : 0,
        'minimum' => $minimum === null ? 0 : $minimum,
        'maximum' => $maximum === null ? 0 : $maximum
    );
}

$tempInterieure = floatval($rapport['temperatures']['mychambre']);
$tempExterieure = floatval($rapport['temperatures']['portail']);
$ecart = round($tempInterieure - $tempExterieure, 1);
$rapport['comparaisons']['temperature'] = array(
    'interieur' => $tempInterieure,
    'exterieur' => $tempExterieure,
    'ecart' => $ecart
);

$reglesMeteo = array(
    array(
        'condition' => function ($ctx) { return $ctx['temp_min'] < $ctx['seuils']['gel']; },
        'niveau' => 'danger',
        'score' => 3,
        'message' => function ($ctx) { return 'Gel prévu '.$ctx['libelle']; }
    ),
    array(
        'condition' => function ($ctx) { return $ctx['temp_min'] < $ctx['seuils']['froid'] && $ctx['temp_min'] >= $ctx['seuils']['gel']; },
        'niveau' => 'warning',
        'score' => 2,
        'message' => function ($ctx) { return 'Froid prévu '.$ctx['libelle']; }
    ),
    array(
        'condition' => function ($ctx) { return $ctx['temp_max'] > $ctx['seuils']['canicule']; },
        'niveau' => 'danger',
        'score' => 3,
        'message' => function ($ctx) { return 'Canicule '.$ctx['libelle']; }
    ),
    array(
        'condition' => function ($ctx) { return $ctx['temp_max'] > $ctx['seuils']['forteChaleur'] && $ctx['temp_max'] <= $ctx['seuils']['canicule']; },
        'niveau' => 'warning',
        'score' => 2,
        'message' => function ($ctx) { return 'Forte chaleur '.$ctx['libelle']; }
    ),
    array(
        'condition' => function ($ctx) { $condition = strtolower($ctx['condition']); return strpos($condition, 'pluie') !== false || strpos($condition, 'averse') !== false; },
        'niveau' => 'info',
        'score' => 1,
        'message' => function ($ctx) { return 'Pluie '.$ctx['libelle']; }
    ),
    array(
        'condition' => function ($ctx) { return strpos(strtolower($ctx['condition']), 'orage') !== false; },
        'niveau' => 'danger',
        'score' => 3,
        'message' => function ($ctx) { return 'Orage '.$ctx['libelle']; }
    ),
    array(
        'condition' => function ($ctx) { return strpos(strtolower($ctx['condition']), 'vent') !== false; },
        'niveau' => 'warning',
        'score' => 2,
        'message' => function ($ctx) { return 'Vent '.$ctx['libelle']; }
    )
);

foreach ($rapport['meteo'] as $jour) {
    $ctx = array(
        'temp_min' => floatval($jour['temp_min']),
        'temp_max' => floatval($jour['temp_max']),
        'condition' => $jour['condition'],
        'libelle' => $jour['jour'].' '.$jour['date'],
        'seuils' => $config['seuils']
    );

    $matches = rapportEvaluerRegles($ctx, $reglesMeteo);
    foreach ($matches as $match) {
        rapportAjouterAlerte($rapport, $match['niveau'], 'meteo', $match['message'], 'meteo');
    }
}

$libellesConsommation = array(
    'electricite' => 'Consommation électrique',
    'eau' => 'Consommation d\'eau',
    'chauffage' => 'Temps de chauffage'
);

foreach ($rapport['historique'] as $nom => $historique) {
    $joursConsecutifs = 0;
    $datePrecedente = null;
    $dernierDepassement = null;
    $libelle = isset($libellesConsommation[$nom]) ? $libellesConsommation[$nom] : ucfirst($nom);

    usort($historique, function ($a, $b) {
        return strtotime(str_replace('/', '-', $a['date'])) <=> strtotime(str_replace('/', '-', $b['date']));
    });

    foreach ($historique as $jour) {
        $dateJour = strtotime(str_replace('/', '-', $jour['date']));
        $moyenne31 = 0;
        $nombreJoursMoyenne = 0;

        for ($i = 1; $i <= 31; $i++) {
            $dateMoyenne = date('d/m/Y', strtotime('-'.$i.' days', $dateJour));
            if (isset($rapport['historique31Jours'][$nom][$dateMoyenne])) {
                $moyenne31 += $rapport['historique31Jours'][$nom][$dateMoyenne];
                $nombreJoursMoyenne++;
            }
        }

        $moyenneReference = $nombreJoursMoyenne > 0 ? $moyenne31 / $nombreJoursMoyenne : 0;
        $depasseMoyenne = $nombreJoursMoyenne > 0 && floatval($jour['valeur']) > ($moyenneReference * $config['seuilMoyenneConso']);
        $jourApresPrecedent = $datePrecedente !== null && $dateJour === strtotime('+1 day', $datePrecedente);

        if ($depasseMoyenne && $jourApresPrecedent) {
            $joursConsecutifs++;
        } elseif ($depasseMoyenne) {
            $joursConsecutifs = 1;
        } else {
            $joursConsecutifs = 0;
        }

        if ($depasseMoyenne) {
            $dernierDepassement = array(
                'nombre' => $joursConsecutifs,
                'date' => $jour['date']
            );
        } else {
            $dernierDepassement = null;
        }

        $datePrecedente = $dateJour;
    }

    if ($dernierDepassement !== null && $dernierDepassement['nombre'] >= 2) {
        $message = $libelle.' supérieure à la moyenne de référence de 20 % pendant '.$dernierDepassement['nombre'].' jours consécutifs';
        rapportAjouterAlerte($rapport, 'warning', 'consommation', $message, $nom);
    }
}

if (abs($ecart) >= $config['seuils']['ecartTemperature']) {
    rapportAjouterAlerte($rapport, 'info', 'temperature', 'Écart intérieur / extérieur de '.$ecart.' °C');
}

$rapport['statistiques']['messages'] = array(
    'total' => count($rapport['messages']),
    'plugins' => array()
);

foreach ($rapport['messages'] as $message) {
    $plugin = ($message['plugin'] == '') ? 'Inconnu' : $message['plugin'];
    if (!isset($rapport['statistiques']['messages']['plugins'][$plugin])) {
        $rapport['statistiques']['messages']['plugins'][$plugin] = 0;
    }
    $rapport['statistiques']['messages']['plugins'][$plugin]++;
}

$priorite = array('danger' => 1, 'warning' => 2, 'info' => 3);
usort($rapport['alertes'], function ($a, $b) use ($priorite) {
    return $priorite[$a['niveau']] <=> $priorite[$b['niveau']];
});

$rapport['resume'] = array(
    'nb_alertes' => count($rapport['alertes']),
    'texte' => count($rapport['alertes']) === 0
        ? 'Aucune anomalie détectée.'
        : (count($rapport['alertes']) === 1 ? 'Une alerte nécessite votre attention.' : count($rapport['alertes']).' alertes nécessitent votre attention.')
);

$html = rapportBuildHtml($rapport);
$scenario->setTags(array(
    'rapport' => json_encode($rapport, JSON_UNESCAPED_UNICODE),
    'rapport_html' => $html
));

$scenario->setLog('');
$scenario->setLog('================================================');
$scenario->setLog('VALIDATION DU RAPPORT DOMOTIQUE');
$scenario->setLog('================================================');
$scenario->setLog('Période : '.$rapport['periode']['debut'].' -> '.$rapport['periode']['fin']);
$scenario->setLog('Soleil : '.$rapport['soleil']['lever'].' -> '.$rapport['soleil']['coucher'].' ('.$rapport['soleil']['duree'].')');
$scenario->setLog('Résumé : '.$rapport['resume']['texte']);
$scenario->setLog('Alertes : '.count($rapport['alertes']));
$scenario->setLog('Messages Jeedom : '.$rapport['statistiques']['messages']['total']);

$message = array(
    'title' => 'Rapport Domotique',
    'message' => $html
);

$mailCommands = array(
    '#[Connexions][SMTP Gmail][Mail to Radiogrange gmail]#',
    '#[Connexions][SMTP Gmail][Mail to Emma gmail]#'
);

foreach ($mailCommands as $mailCommand) {
    $cmdMail = cmd::byString($mailCommand);
    if (is_object($cmdMail)) {
        $cmdMail->execCmd($message);
        $scenario->setLog('Mail envoyé : '.$mailCommand);
    } else {
        $scenario->setLog('Commande SMTP introuvable : '.$mailCommand);
    }
}

$scenario->setLog('Taille HTML : '.strlen($html).' octets');
