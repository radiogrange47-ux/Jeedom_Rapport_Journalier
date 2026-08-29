# Rapport Journalier Jeedom

## 1. Objectif

Le projet a pour objectif de générer un rapport quotidien de suivi de la maison et des consommations à partir des objets Jeedom, des prévisions météo, des historiques et des alertes fonctionnelles.

Le script principal, [Rapport_Journalier.php](Rapport_Journalier.php), doit :

- récupérer des données depuis des commandes Jeedom,
- comparer les tendances météo et de consommation,
- détecter les anomalies et seuils critiques,
- produire un résumé exploitable en log et en HTML,
- envoyer un ou plusieurs emails de synthèse.

Le résultat attendu est un bilan lisible, cohérent et automatiquement exploitable dans un contexte domotique.

---

## 2. Périmètre du projet

Ce dépôt est volontairement simple et centré sur une seule logique applicative :

- [Rapport_Journalier.php](Rapport_Journalier.php) : script principal exécuté par un scénario Jeedom,
- [PROJECT.md](PROJECT.md) : documentation de référence du projet.

Les évolutions doivent rester cohérentes avec cette architecture légère et monolithique, sans introduire de dépendances ou de modules additionnels non nécessaires.

---

## 3. Architecture fonctionnelle

Le script est organisé en blocs logiques, dans l’ordre suivant :

### 3.1. Bloc d’acquisition des données

- initialisation du tableau de rapport,
- chargement de la configuration des seuils,
- définition des fonctions utilitaires communes,
- lecture des heures de lever/coucher du soleil,
- récupération des historiques, météo, températures et messages Jeedom,
- stockage temporaire des données dans un tableau structuré.

### 3.2. Bloc de traitement

- calcul des moyennes et des tendances météo,
- calcul des variations et statistiques sur les historiques,
- comparaison intérieur/extérieur pour les températures,
- détection d’alertes selon les seuils configurés,
- synthèse du résumé final.

### 3.3. Bloc de validation

- contrôle de la cohérence des données,
- génération de logs métier explicites,
- vérification de la présence de données et de l’état général du rapport.

### 3.4. Bloc de génération HTML et envoi mail

- création du document HTML du rapport,
- mise en forme visuelle avec CSS intégré,
- minification du HTML avant envoi,
- envoi aux adresses e-mail configurées via les commandes SMTP Jeedom,
- conservation du rapport dans les tags du scénario.

---

## 4. Structure des données

Le rapport est construit comme un tableau associatif centralisé, avec des clés principales :

- configuration
- periode
- soleil
- meteo
- historique
- temperatures
- messages
- comparaisons
- statistiques
- alertes
- resume

Cette structure doit rester stable et cohérente. Toute nouvelle information ajoutée au rapport doit respecter la logique de lecture déjà en place et conserver une clé explicite.

---

## 5. Règles de développement

### 5.1. Langue et lisibilité

- le code et les messages doivent être en français lorsque c’est possible,
- les libellés doivent rester clairs, exprimer l’action et rester compréhensibles dans un contexte domotique,
- les logs doivent être utiles pour diagnostiquer rapidement les erreurs ou anomalies.

### 5.2. Conventions de nommage

- les fonctions utilitaires doivent commencer par le préfixe `rapport`, par exemple : `rapportNomJour`, `rapportAjouterAlerte`, `rapportIconeMeteo`,
- les clés du tableau de rapport doivent être nommées de manière explicite et normalisées, en minuscule et sans espace,
- les libellés d’alerte doivent suivre un niveau standard : `danger`, `warning`, `info`.

### 5.3. Seuils et configuration

- les valeurs critiques doivent être centralisées dans `configuration`,
- il faut éviter de disperser des seuils dans le code sans contexte,
- toute modification d’un seuil doit être validée comme une évolution du comportement du rapport.

### 5.4. Robustesse

- les données externes doivent être testées avant utilisation,
- les valeurs absentes ou introuvables doivent produire des données de secours ou des alertes explicites,
- les opérations doivent rester tolérantes aux cas où une commande Jeedom manque ou retourne une valeur vide.

### 5.5. Modifications autorisées

- les modifications doivent rester concentrées sur la logique du rapport,
- le code doit préserver la séquence logique : acquisition → traitement → validation → génération → envoi,
- toute nouvelle fonctionnalité doit être documentée ici dans [PROJECT.md](PROJECT.md).

---

## 6. Conventions de qualité

- préférer des fonctions courtes et spécialisées plutôt qu’un script monolithique sans structure,
- garder les structures de données lisibles et sérialisables,
- conserver un rendu HTML propre et exploitable sans dépendance extérieure,
- ne pas laisser de code mort ou de doublons inutiles,
- valider logiquement les résultats avant d’envoyer un mail.

---

## 7. Règles de maintenance

- toute évolution majeure du rapport doit être décrite dans ce fichier,
- les seuils, noms de commandes et identifiants Jeedom doivent être vérifiés avant toute mise en production,
- les messages d’alerte doivent rester cohérents avec le niveau réel de criticité,
- les modifications doivent rester compatibles avec les scénarios existants et les tags déjà utilisés.

---

## 8. Résumé du projet

Ce projet vise à fournir un rapport automatique de santé d’une installation domotique, avec un focus sur :

- la météo,
- les consommations,
- les températures,
- les événements Jeedom,
- les alertes d’anomalie.

L’objectif final est de transformer des données brutes en un document synthétique prêt à être lu et actionné rapidement par l’utilisateur.
