# Shortcodes und Platzhalter

## Shortcodes für Bestätigungs- und Fehlerseite

### `[doi_confirmation_status]`

Zeigt, was mit dem Link geschehen ist, den der Besucher gerade angeklickt hat. Jeder Text lässt sich über ein Attribut ersetzen:

| Attribut | Wann es greift | Standardtext |
|---|---|---|
| `confirmed` | Link bestätigt die Adresse | Danke! Deine E-Mail-Adresse ist bestätigt. |
| `already_confirmed` | Link wurde schon verwendet | Dein Opt-In wurde bereits bestätigt. |
| `expired` | Frist abgelaufen | Dieser Bestätigungslink ist abgelaufen. Bitte sende das Formular erneut ab. |
| `not_found` | Link unbekannt oder verändert | Dieser Bestätigungslink ist ungültig. |
| `none` | Seite ohne Link aufgerufen | leer, es erscheint nichts |

Beispiel: `[doi_confirmation_status confirmed="Willkommen an Bord!"]`

Steht der Shortcode nicht auf der Seite, bekommt ein abgelaufener, bereits verwendeter oder ungültiger Link trotzdem eine kurze Meldung vor dem Seiteninhalt.

![Bestätigungsseite](images/de/confirmed-page.png)

### `[doi_field name="…" default="…"]`

Gibt einen Wert aus dem bestätigten Formular aus, zum Beispiel `[doi_field name="your-name" default="Hallo"]`. Der Wert erscheint nur in dem Aufruf, der die Anmeldung bestätigt hat. Sonst, oder wenn das Feld leer ist, steht der Wert von `default`.

### `[doi_if status="…"]…[/doi_if]`

Zeigt den eingeschlossenen Inhalt nur, wenn das Ergebnis des Links zu `status` passt. Mehrere Werte werden mit Komma getrennt: `confirmed`, `already_confirmed`, `expired`, `not_found`, `none`.

Beispiel: `[doi_if status="expired"]Der Link ist abgelaufen. Eine neue Anmeldung ist jederzeit möglich.[/doi_if]`

![Seite bei ungültigem Link](images/de/invalid-link.png)

### `[doi_error_message default="…"]`

Für die **Fehler-Weiterleitungsseite**. Erklärt anhand des Parameters `doi_error` in der Adresse, warum eine Anmeldung abgelehnt wurde. Ohne diesen Parameter erscheint nichts. Bei unbekanntem Code steht der Wert von `default`.

| Code | Meldung |
|---|---|
| `rate_limit_ip` | Zu viele Anmeldungen von derselben Verbindung in kurzer Zeit. |
| `rate_limit_email` | Diese Adresse wurde mehrfach in kurzer Zeit angemeldet. |
| `recipient_invalid` | Diese E-Mail-Adresse kann keine Post empfangen. |
| `no_recipient` | Es wurde keine gültige E-Mail-Adresse eingegeben. |
| `unique_email_duplicate` | Diese E-Mail-Adresse ist bereits angemeldet. |
| `consent_not_given` | Zustimmung zum Einwilligungstext fehlt. |
| `save_failed` | Die Anmeldung konnte nicht gespeichert werden. |
| `submission_cancelled` | Die Anmeldung konnte nicht abgeschlossen werden. |

Die Meldungen gelten in der Sprache der Website; sie lassen sich über den Filter `f12_doi_error_messages` anpassen. Die Texte in der Tabelle sind sinngemäß gekürzt.

## Platzhalter in der Bestätigungsmail

Platzhalter stehen in eckigen Klammern im Nachrichtentext und werden beim Versand ersetzt. Unter **Platzhalter anzeigen** im Reiter **E-Mail** listet das Plugin die Standardplatzhalter.

### Systemplatzhalter

| Platzhalter | Inhalt |
|---|---|
| `[doubleoptinlink]` | persönlicher Bestätigungslink, in jeder Mail Pflicht |
| `[doubleoptoutlink]` | Abmeldelink; die Abmeldeseite stellt das Opt-Out-Addon bereit |
| `[doubleoptin_form_date]` | Datum der Anmeldung im Datumsformat der Website |
| `[doubleoptin_form_time]` | Uhrzeit im Zeitformat der Website |
| `[doubleoptin_form_url]` | Adresse der Seite, auf der das Formular abgeschickt wurde |
| `[doubleoptin_form_subject]` | Betreff des Formulars |
| `[doubleoptin_form_email]` | Administrator-Adresse der Website |
| `[doubleoptin_privacy_url]` | Adresse der Datenschutzerklärung (**Einstellungen → Datenschutzerklärungs-Seite**, sonst die WordPress-Datenschutzseite) |

### Formularfelder

Diese Platzhalter werden aus den Feldern des Formulars gefüllt. Das Plugin erkennt Felder anhand üblicher Namen wie `your-email` oder `first-name`. Abweichende Namen ordnest du im Reiter **Zuordnung** zu. Ohne Treffer bleibt der Platzhalter leer.

`[doi_email]`, `[doi_name]`, `[doi_first_name]`, `[doi_last_name]`, `[doi_phone]`, `[doi_company]`, `[doi_subject]`, `[doi_message]`, `[doi_address]`, `[doi_city]`, `[doi_zip]`, `[doi_country]`
