# Shortcodes and placeholders

## Shortcodes for the confirmation and error pages

### `[doi_confirmation_status]`

Shows what happened to the link the visitor just clicked. Each text can be replaced through an attribute:

| Attribute | When it applies | Default text |
|---|---|---|
| `confirmed` | The link confirms the address | Thank you! Your email address has been confirmed. |
| `already_confirmed` | The link was used before | Your opt-in has already been confirmed. |
| `expired` | The period has passed | This confirmation link has expired. Please submit the form again. |
| `not_found` | Unknown or altered link | This confirmation link is invalid. |
| `none` | Page opened without a link | empty, nothing is shown |

Example: `[doi_confirmation_status confirmed="Welcome aboard!"]`

Without the shortcode on the page, an expired, already used or invalid link still gets a short notice in front of the page content.

![Confirmation page](images/en/confirmed-page.png)

### `[doi_field name="…" default="…"]`

Prints a value from the confirmed form, for example `[doi_field name="your-name" default="Hello"]`. The value appears only in the request that confirmed the sign-up. Otherwise, or if the field is empty, the value of `default` is shown.

### `[doi_if status="…"]…[/doi_if]`

Shows the enclosed content only when the outcome of the link matches `status`. Separate several values with commas: `confirmed`, `already_confirmed`, `expired`, `not_found`, `none`.

Example: `[doi_if status="expired"]The link has expired. You can sign up again at any time.[/doi_if]`

![Page for an invalid link](images/en/invalid-link.png)

### `[doi_error_message default="…"]`

For the **Error redirect page**. Explains, based on the `doi_error` parameter in the address, why a sign-up was refused. Without the parameter nothing is shown. For an unknown code the value of `default` appears.

| Code | Message |
|---|---|
| `rate_limit_ip` | Too many sign-ups from your connection in a short time. |
| `rate_limit_email` | This address was signed up several times in a short time. |
| `recipient_invalid` | This email address cannot receive mail. |
| `no_recipient` | No valid email address was entered. |
| `unique_email_duplicate` | This email address is already signed up. |
| `consent_not_given` | Please agree to the consent text to sign up. |
| `save_failed` | The sign-up could not be saved. |
| `submission_cancelled` | The sign-up could not be completed. |

The messages can be changed with the filter `f12_doi_error_messages`. The texts in the table are shortened.

## Placeholders in the confirmation email

Placeholders are written in square brackets in the message body and replaced when the mail is sent. Under **Show placeholders** on the **Email** tab the plugin lists the standard placeholders.

### System placeholders

| Placeholder | Content |
|---|---|
| `[doubleoptinlink]` | personal confirmation link, required in every mail |
| `[doubleoptoutlink]` | unsubscribe link; the unsubscribe page is provided by the Opt-Out add-on |
| `[doubleoptin_form_date]` | date of the sign-up in the site's date format |
| `[doubleoptin_form_time]` | time in the site's time format |
| `[doubleoptin_form_url]` | address of the page the form was submitted on |
| `[doubleoptin_form_subject]` | subject of the form |
| `[doubleoptin_form_email]` | the site's administrator address |
| `[doubleoptin_privacy_url]` | address of the privacy policy (**Settings → Privacy Policy Page**, otherwise the WordPress privacy page) |

### Form fields

These placeholders are filled from the form's fields. The plugin recognizes fields by common names such as `your-email` or `first-name`. Assign differently named fields on the **Mapping** tab. Without a match the placeholder stays empty.

`[doi_email]`, `[doi_name]`, `[doi_first_name]`, `[doi_last_name]`, `[doi_phone]`, `[doi_company]`, `[doi_subject]`, `[doi_message]`, `[doi_address]`, `[doi_city]`, `[doi_zip]`, `[doi_country]`
