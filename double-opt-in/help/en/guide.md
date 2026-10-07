# Setting up Double Opt-In

Double Opt-In makes a sign-up count only after the email address has been confirmed with a click on a link. The original form email is delivered after that confirmation. The free plugin supports Contact Form 7. Other form plugins are connected through add-ons of the Pro bundle.

## Setup wizard

After activation the setup wizard opens once. It can be skipped and started again under **Double Opt-In → Settings → Start setup wizard**. Four steps, all prefilled:

1. **Sender:** name and address of the confirmation email, ideally an address on your own domain.
2. **Forms:** the forms that should ask for confirmation, with their email field. Forms that already use Double Opt-In stay as they are.
3. **Confirmation email:** subject and one of four designs (Light, Dark, Accent, Text only).
4. **After the click:** create the page "Email address confirmed" or choose an existing page. **Send me a test email** sends a sample whose link confirms nothing (at most five per hour).

Forms are switched on only at the end, and only if their configuration is complete.

![Setup wizard, forms step](images/en/setup-wizard.png)

![Setup wizard, sender step](images/en/wizard-sender.png)

![Setup wizard, confirmation mail step with design choice](images/en/wizard-mail.png)

![Setup wizard, after-the-click step with test email](images/en/wizard-test.png)

## Configuring a form

Under **Double Opt-In → Forms** the overview lists all detected forms. A form's switch can be turned on only when three required settings are present. The banner in the form names what is missing.

![Forms overview](images/en/forms.png)

| Required setting | Where | Note |
|---|---|---|
| Recipient field | **Email** tab | The form field that holds the email address, usually `your-email` in Contact Form 7 |
| Subject | **Email** tab | must not be empty |
| Message body or template | **Email** tab | a custom text must contain `[doubleoptinlink]` |

![Email tab](images/en/form-email-tab.png)

The **General** tab holds the **Consent text (GDPR)**, the **Consent acceptance field**, the **Category**, the **Conditional trigger**, the **Confirmation page** and the **Error redirect page**. With an acceptance field selected, sign-ups without the checkmark are rejected. The **Mapping** tab is optional.

![General tab](images/en/form-settings.png)

![Confirmation mail in the "Accent" design](images/en/confirmation-mail-design.png)

## Confirmation page

The link in the email leads to the selected confirmation page, or to the home page if none is selected. On the page the shortcode `[doi_confirmation_status]` shows the outcome of the link; all shortcodes are listed on the "Shortcodes and placeholders" page.

![Confirmation page](images/en/confirmed-page.png)

## Testing and checking

Submit the form, open the confirmation email, click the link. Under **Double Opt-In → Opt-Ins** the record appears with the status **Confirmed**. The detail view shows timestamps, IP addresses, the consent text and the delivery status of the confirmation email. While testing, the sign-up limit applies: by default three sign-ups per address and five per IP per hour.

![Opt-in list](images/en/optins.png)

![Opt-in detail with the delivery status "Could not be sent"](images/en/optin-mail-failed.png)

## Key settings

Under **Double Opt-In → Settings**:

| Setting | Effect | Default |
|---|---|---|
| **Token Expiry (Hours)** | Validity of the confirmation link | 48 |
| **Delete Opt-Ins after** | Retention of confirmed opt-ins; `0` turns it off | 12 months |
| **Delete Unconfirmed after** | Retention of unconfirmed sign-ups; `0` turns it off | 7 months |
| **Rate Limit IP** / **Rate Limit Email** | Sign-ups per time window | 5 / 3 |
| **Rate Limit Window (Min)** | Length of the time window | 60 |

Deletion is physical and runs once a day. The record is also the proof of consent; very short periods weaken it.

Three more areas of the interface: **Categories** groups opt-ins, **Database** offers delete actions for opt-ins and a reset of the tables, and the **Audit log** lists system events and changes with timestamp and severity.

![Categories page](images/en/categories.png)

![Database management page](images/en/database.png)

![Audit log](images/en/audit.png)

## Notes

- **Site Health:** Under **Tools → Site Health** the plugin checks the sender domain (SPF, DMARC), failed confirmation emails and orphaned acceptance fields.
- Help with problems is on the "Troubleshooting" page.
