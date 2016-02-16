# OpenBadger

## Overview
OpenBadger ist "Mozilla OpenBadge Issuer" Plugin für Wordpress, mit Hilfe dessen eine Blidungsanbieter offene Zertifikate (open badges) erstellen und an Besucher verleihen kann. Die Programmierung basiert vor allem auf den wpBadger Plugins von Dave Lester und deren Forks. Besondern Dank an Steven Butler, der den "OpenBadge Designer" integriert hat.

##Installation in Wordpress
Benötigt Wordpress ab Version 3.4 (getestet bis Version 3.6.1)

1. Entpacke das OpenBadger in einen Ordner open-badger in das Plugin Verzeichnis /wp-content/plugins/.
2. Aktiviere das OpenBadger plugin wie jedes andere Plugin auch (WPBadger sollte nicht aktiv sein, um konflikte zu vermmeiden).
3. Konfiguriere das Plugin im Dashboard -> Einstellungen -> OpenBadger.

##Vergabe von Zertifikaten / AUszeichnungn /Badges
1. Erstelle als erstes eine neue Auszeichnung (Titel, Beschreibung und Kriterien sowie ein Bild sind Pflicht)
2. Verleihe dieses Zertikat an eine Person, in dem du rechts in der InfoBox das gewünschte Zertifikat auswählst und die Emailadresse der Person, der du das Zertifikat verleihen möchtest. Belege im Inhalt, warum die Person das Zertifikat verliehen bekommt (Evidenz)
3. Die Person bekommt mit dem Abspeichern der Verleihung eine Email mit der Anleitung, wie sich dieses Zertifikat in ihren OpenBadge BackPack speichern kann.

##Shortcodes
[ mybadges ]
Zeigt einem Besucher alle Auszeichnungen an, die ihm unter der angegeben Email verliehen wurden.

##FAQ

    #Welche Sprachen werden unterstützt?
    Übersetzungen DE / EN . Eigene Übersetzungen können im Verzeichnis /languages/ des Plugin Ordners hinzugefügt werden.
    #Ist das Plugin multisite kompatibel?
    Ja!

## Vorschlag für das Emailtemplate auf der Konfigurationsseite:

Das folgende Zertifikat

<img alt="" src="{BADGE_IMAGE_URL}" />
<strong><a href="{BADGE_URL}">{AWARD_TITLE}</a></strong>
<em><strong>{BADGE_DESCRIPTION}</strong></em>

{EVIDENCE}.

<strong>Die Verleihung des Zertifikates wurde <a href="{AWARD_URL}">hier veröffentlicht</a> und kann <a href="{AWARD_ACCEPT_URL">über diesen Link</a>angennommen werden</strong>.

Dieses Zertifikat ist kompatibel mit dem <a href="http://openbadges.org/about/">OpenBadge</a> Standard von Mozilla.

Wir empfehlen, dieses Zertifikat als Beleg Ihrer Kompetenzen zu Ihrer <a href="http://backpack.openbadges.org">BadgePack</a> hinzuzufügen, um sich damit auch in anderen Netzwerken ausweisen zu können.
Registrieren Sie sich kostenlos bei Mozilla und richten Sie sich Ihren Kompetenz-Rucksack ein: <a href="http://backpack.openbadges.org">Mozilla BackPack</a>.

<hr />

<strong>Zertifikat jetzt <a href="{AWARD_ACCEPT_URL}">{BADGE_TITLE} annehmen</a></strong>.

<hr />

Aussteller des Zertifkikates (Offizieller OpenBadge Issuer):
<em>{ISSUER_ORG}
{ISSUER_NAME}
{ISSUER_CONTACT}</em>
