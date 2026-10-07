# Double Opt-In einrichten

Double Opt-In sorgt dafür, dass eine Anmeldung erst gilt, wenn die E-Mail-Adresse per Klick auf einen Link bestätigt wurde. Die ursprüngliche Formular-Mail wird erst nach dieser Bestätigung zugestellt. Das kostenlose Plugin unterstützt Contact Form 7. Weitere Formular-Plugins werden über Addons des Pro-Bundles angebunden.

## Einrichtungsassistent

Nach der Aktivierung öffnet sich einmalig der Einrichtungsassistent. Er lässt sich überspringen und unter **Double Opt-In → Einstellungen → Einrichtungsassistent starten** erneut aufrufen. Vier Schritte, alle vorbelegt:

1. **Absender:** Name und Adresse der Bestätigungsmail, am besten mit einer Adresse der eigenen Domain.
2. **Formulare:** die Formulare, die eine Bestätigung verlangen sollen, mit dem E-Mail-Feld. Formulare, die Double Opt-In bereits nutzen, bleiben unverändert.
3. **Bestätigungsmail:** Betreff und eines von vier Designs (Hell, Dunkel, Akzent, Nur Text).
4. **Nach dem Klick:** die Seite „E-Mail-Adresse bestätigt" neu anlegen oder eine vorhandene Seite wählen. **Testmail an mich senden** verschickt eine Probe, deren Link nichts bestätigt (höchstens fünf pro Stunde).

Formulare werden erst am Ende eingeschaltet, und nur, wenn ihre Konfiguration vollständig ist.

![Einrichtungsassistent, Schritt Formulare](images/de/setup-wizard.png)

![Einrichtungsassistent, Schritt Absender](images/de/wizard-sender.png)

![Einrichtungsassistent, Schritt Bestätigungsmail mit Design-Auswahl](images/de/wizard-mail.png)

![Einrichtungsassistent, Schritt Nach dem Klick mit Testmail](images/de/wizard-test.png)

## Formular konfigurieren

Unter **Double Opt-In → Formulare** zeigt die Übersicht alle erkannten Formulare. Der Schalter eines Formulars lässt sich erst umlegen, wenn drei Pflichtangaben vorhanden sind. Fehlendes nennt das Banner im Formular.

![Formularübersicht](images/de/forms.png)

| Pflichtangabe | Wo | Hinweis |
|---|---|---|
| Empfängerfeld | Reiter **E-Mail** | Das Formularfeld mit der E-Mail-Adresse, bei Contact Form 7 meist `your-email` |
| Betreff | Reiter **E-Mail** | darf nicht leer sein |
| Nachrichtentext oder Vorlage | Reiter **E-Mail** | ein eigener Text muss `[doubleoptinlink]` enthalten |

![Reiter E-Mail](images/de/form-email-tab.png)

Auf dem Reiter **Allgemein** stehen der **Einwilligungstext (DSGVO)**, das **Feld zur Einwilligungsannahme**, die **Kategorie**, der **Bedingte Auslöser**, die **Bestätigungsseite** und die **Fehler-Weiterleitungsseite**. Ist ein Annahmefeld gewählt, werden Anmeldungen ohne Häkchen abgewiesen. Der Reiter **Zuordnung** ist optional.

![Reiter Allgemein](images/de/form-settings.png)

![Bestätigungsmail im Design „Akzent"](images/de/confirmation-mail-design.png)

## Bestätigungsseite

Der Link in der Mail führt auf die gewählte Bestätigungsseite, ohne Auswahl auf die Startseite. Auf der Seite zeigt der Shortcode `[doi_confirmation_status]` das Ergebnis des Links; alle Shortcodes stehen auf der Seite „Shortcodes und Platzhalter".

![Bestätigungsseite](images/de/confirmed-page.png)

## Testen und prüfen

Formular absenden, Bestätigungsmail öffnen, Link anklicken. Unter **Double Opt-In → Opt-Ins** erscheint der Datensatz mit Status **Bestätigt**. Im Detail stehen Zeitpunkte, IP-Adressen, Einwilligungstext und der Versandstatus der Bestätigungsmail. Beim Testen greift die Anmeldegrenze: standardmäßig drei Anmeldungen pro Adresse und fünf pro IP je Stunde.

![Opt-In-Liste](images/de/optins.png)

![Opt-In-Detail mit dem Zustellstatus „Konnte nicht verschickt werden"](images/de/optin-mail-failed.png)

## Wichtige Einstellungen

Unter **Double Opt-In → Einstellungen**:

| Einstellung | Wirkung | Voreinstellung |
|---|---|---|
| **Token-Ablauf (Stunden)** | Gültigkeit des Bestätigungslinks | 48 |
| **Opt-Ins löschen nach** | Löschfrist bestätigter Opt-Ins; `0` schaltet ab | 12 Monate |
| **Unbestätigte löschen nach** | Löschfrist unbestätigter Anmeldungen; `0` schaltet ab | 7 Monate |
| **Rate Limit IP** / **Rate Limit E-Mail** | Anmeldungen je Zeitfenster | 5 / 3 |
| **Rate-Limit-Fenster (Min)** | Länge des Zeitfensters | 60 |

Gelöscht wird physisch, einmal täglich. Der Datensatz ist zugleich der Einwilligungsnachweis; zu kurze Fristen schwächen ihn.

Drei weitere Bereiche der Oberfläche: **Kategorien** gruppiert Opt-Ins, **Datenbank** bietet Löschaktionen für Opt-Ins und das Zurücksetzen der Tabellen, und das **Audit-Log** listet Systemereignisse und Änderungen mit Zeitstempel und Schweregrad.

![Kategorien-Seite](images/de/categories.png)

![Datenbankverwaltung](images/de/database.png)

![Audit-Log](images/de/audit.png)

## Hinweise

- **Website-Zustand:** Unter **Werkzeuge → Website-Zustand** prüft das Plugin die Absenderdomain (SPF, DMARC), fehlgeschlagene Bestätigungsmails und verwaiste Zustimmungsfelder.
- Hilfe bei Problemen steht auf der Seite „Fehlerbehebung".
