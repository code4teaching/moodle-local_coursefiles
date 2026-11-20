<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings (German).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['autoapprove'] = 'Kursanträge automatisch annehmen';
$string['autoapprove_desc'] = 'Folgt ein Antrag dem Namensschema unten und hat die Klasse schon einen Kurs, wird der Kurs sofort angelegt: verborgen, im Kursbereich des neuesten Kurses dieser Klasse (Archiv-Bereiche zählen nicht), mit der antragstellenden Person als Trainer/in. Sonst bleibt es ein normaler Antrag. Das voreingestellte Schema ist „Schuljahr Klasse Kürzel Fach“, z. B. 2026 11FI2 SebH Objektorientierte Programmierung, Kurzname 2026 11FI2 SebH OOP.';
$string['autoapprovearchive'] = 'Archiv-Bereiche';
$string['autoapprovearchive_desc'] = 'Kursbereiche, deren Pfad diesen Text enthält (Groß-/Kleinschreibung egal), zählen bei der Suche nach dem Bereich einer Klasse nicht. Leer: keine.';
$string['autoapprovedbody'] = '{$a->requester} hat einen Kurs beantragt, er wurde sofort angelegt.

Name: {$a->fullname}
Kurzname: {$a->shortname}
Kursbereich: {$a->category}

Der Kurs ist verborgen. Bitte Name und Kursbereich prüfen: {$a->url}';
$string['autoapprovedsubject'] = 'Kurs automatisch angelegt: {$a->fullname}';
$string['autoapprovefullname'] = 'Schema für den vollständigen Namen';
$string['autoapprovefullname_desc'] = 'Regulärer Ausdruck mit Begrenzern. Die Gruppe (?&lt;class&gt;…) ist die Klasse; die Kurse derselben Klasse bestimmen den Kursbereich. Eine optionale Gruppe (?&lt;key&gt;…) muss in vollständigem und Kurznamen gleich sein.';
$string['autoapproveheading'] = 'Kursanträge';
$string['autoapproveheading_desc'] = 'Lehrkräfte beantragen Kurse aus VisuDesk. Kurse können sofort angelegt und danach geprüft werden.';
$string['autoapprovenotify'] = 'Benachrichtigung über angelegte Kurse';
$string['autoapprovenotify_desc'] = 'Anmeldename der Person, die automatisch angelegte Kurse prüft. Leer: die Hauptadministration.';
$string['autoapproveshortname'] = 'Schema für den Kurznamen';
$string['autoapproveshortname_desc'] = 'Regulärer Ausdruck mit Begrenzern, mit denselben Gruppen (?&lt;class&gt;…) und gegebenenfalls (?&lt;key&gt;…) wie beim vollständigen Namen.';
$string['availabilitydisabled'] = 'Voraussetzungen sind auf dieser Website ausgeschaltet (Website-Administration → Erweiterte Funktionen → Voraussetzungen aktivieren). Ein Freigabedatum hätte deshalb keine Wirkung.';
$string['connectbutton'] = 'Mit VisuDesk verbinden';
$string['connectinvalid'] = 'Dieser Link zum Verbinden einer App ist ungültig. Bitte in der App noch einmal auf „Mit Moodle verbinden“ klicken.';
$string['connectnoservice'] = 'Der Webservice {$a} steht zum Verbinden von Apps nicht zur Verfügung. Bitte wende dich an die Moodle-Administration.';
$string['connectnotallowed'] = 'Für den Webservice {$a} kannst du noch keinen Schlüssel bekommen. Bitte wende dich an die Moodle-Administration.';
$string['connectquestion'] = '<p>VisuDesk auf diesem Computer möchte mit deinem Moodle-Konto <strong>{$a->name}</strong> arbeiten (Webservice „{$a->service}“).</p><p>VisuDesk kann dann in deinen Kursen das tun, was du in Moodle tun kannst: Dateien hochladen, Tests anlegen und Abgaben ansehen. Der Schlüssel geht nur an VisuDesk auf diesem Computer und wird dort im Schlüsselbund gespeichert.</p><p>Entfernen kannst du ihn jederzeit unter Einstellungen → Sicherheitsschlüssel.</p>';
$string['connecttitle'] = 'VisuDesk verbinden';
$string['errornamepattern'] = 'Bitte einen gültigen regulären Ausdruck mit Begrenzern und einer Gruppe „class“ eingeben, zum Beispiel /^(?<class>\\S+) .+$/u.';
$string['feedbackfilesdisabled'] = 'In dieser Aufgabe sind Feedbackdateien ausgeschaltet. In den Einstellungen der Aufgabe unter Feedback-Typen „Feedbackdateien“ einschalten.';
$string['gradenotinscale'] = 'Diese Note gehört nicht zur Skala der Aufgabe.';
$string['gradetoohigh'] = 'Die Bewertung ist höher als das Maximum von {$a} Punkten.';
$string['gradingstarted'] = 'In dieser Aufgabe hat schon jemand eine Bewertung, deshalb lässt sich die Bewertungsart nicht mehr ändern.';
$string['messageprovider:courseautoapproved'] = 'Kurs automatisch aus einem Antrag angelegt';
$string['nogradetype'] = 'Diese Aufgabe wird nicht bewertet.';
$string['nomarkingworkflow'] = 'Diese Aufgabe hat keinen Bewertungs-Workflow. Freigeben ist nicht nötig, Bewertungen sind sofort sichtbar.';
$string['notparticipant'] = 'Diese Person nimmt nicht an der Aufgabe teil.';
$string['scalenotfound'] = 'Auf dieser Website und in diesem Kurs gibt es keine Skala mit dem Namen „{$a}“.';
