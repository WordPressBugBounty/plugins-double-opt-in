# Fehlerbehebung

## Die Bestätigungsmail kommt nicht an

1. Spam-Ordner prüfen.
2. Unter **Double Opt-In → Opt-Ins** den Filter **Bestätigungsmail** auf **Mail fehlgeschlagen** stellen. Im Detail eines Opt-Ins steht, ob die Mail **an den Mailserver übergeben** wurde oder **nicht verschickt werden konnte**, mit Fehlerhinweis.
3. Nicht verschickt: der Mailversand der Website ist das Problem. Abhilfe schafft meist ein SMTP-Plugin, das über den eigenen Mail-Anbieter sendet. Danach im Opt-In-Detail **E-Mail erneut senden** wählen.
4. Übergeben, aber nicht im Postfach: **Werkzeuge → Website-Zustand** öffnen. Die Prüfung „Absenderdomain der Bestätigungsmail" meldet fehlende SPF- oder DMARC-Einträge und Freemail-Absender. Als Absender gehört eine Adresse der eigenen Domain in die Formulareinstellungen.
5. Beim Testen die Anmeldegrenze beachten: standardmäßig drei Anmeldungen je Adresse und fünf je IP-Adresse pro Stunde (**Einstellungen → Sicherheit**).

## Das Formular wird nicht erkannt

- Das kostenlose Plugin erkennt Contact-Form-7-Formulare. Contact Form 7 muss aktiv und das Formular gespeichert sein.
- Formulare von Elementor Pro, WPForms, Gravity Forms und Avada erscheinen mit dem jeweiligen Addon des Pro-Bundles. Die Formularübersicht weist auf ein fehlendes Addon hin, wenn das Formular-Plugin installiert ist.

![Website-Zustand: Prüfung der Absenderdomain](images/de/health-sender-domain.png)

![Website-Zustand: Hinweis auf nicht verschickte Bestätigungsmails](images/de/health-mail-failures.png)

![Opt-In-Detail mit dem Zustellstatus „Konnte nicht verschickt werden"](images/de/optin-mail-failed.png)

## Der Schalter eines Formulars ist gesperrt

Mindestens eine Pflichtangabe fehlt: **Empfängerfeld**, **Betreff** oder **Nachrichtentext** beziehungsweise Vorlage. Das Banner im Formular nennt sie. Ein eigener Text zählt nur, wenn er `[doubleoptinlink]` enthält. Auch ein Empfängerfeld, das im Formular nicht mehr existiert, gilt als fehlend. Nach dem Ergänzen speichern und den Schalter umlegen.

## Ein Formular wurde nach einem Update deaktiviert

Beim Update werden aktive Formulare auf Vollständigkeit geprüft, und unvollständige angehalten. Fehlende Angabe ergänzen, speichern, Schalter erneut einschalten.

## Besucher können sich nicht anmelden

- **Meldung zur Einwilligung:** Für das Formular ist ein **Feld zur Einwilligungsannahme** gewählt, und das Häkchen fehlt. Anmeldungen ohne Häkchen werden abgewiesen.
- **Zu viele Anmeldungen:** Die Grenzen je IP-Adresse und E-Mail-Adresse sind erreicht. Die Werte stehen unter **Einstellungen → Sicherheit**.
- Contact-Form-7-Formulare mit Double Opt-In enthalten ein verstecktes Feld und eine Mindest-Ausfüllzeit von zwei Sekunden gegen Bots. Wer das Formular schneller abschickt, wird abgewiesen.

![Meldung bei fehlendem Häkchen](images/de/consent-required.png)

## Der Bestätigungslink funktioniert nicht

| Meldung | Ursache |
|---|---|
| Dieser Bestätigungslink ist abgelaufen. | Die Frist unter **Token-Ablauf (Stunden)** ist verstrichen (Voreinstellung 48). Der Besucher meldet sich neu an. |
| Dein Opt-In wurde bereits bestätigt. | Ein Link ist nur einmal verwendbar. |
| Dieser Bestätigungslink ist ungültig. | Der Datensatz existiert nicht mehr, etwa nach einer Löschung, oder der Link wurde verändert. |

Welche Texte erscheinen, steuert die Bestätigungsseite; siehe „Shortcodes und Platzhalter".

![Bestätigungsseite bei ungültigem Link](images/de/invalid-link.png)

## Das Admin-Menü bleibt leer oder lädt nur teilweise

Skripte anderer Plugins oder Themes können Bibliotheken im Admin überschreiben. Plugin und Addons auf den aktuellen Stand bringen und danach die Seite neu laden.

![Dashboard-Hinweis bei auffällig vielen unbestätigten Opt-Ins](images/de/dashboard-unconfirmed.png)

## Weitere Hinweise

- Die Prüfungen unter **Werkzeuge → Website-Zustand** nennen Ursache und nächsten Schritt, auch für fehlende Datenbanktabellen und nicht passende Addon-Versionen. Der Bereich **Website-Zustand → Info** enthält einen Abschnitt „Double Opt-In", den man einer Support-Anfrage beilegen kann.
- Ein Hinweis im Dashboard weist darauf hin, wenn auffällig viele Opt-Ins unbestätigt bleiben.
