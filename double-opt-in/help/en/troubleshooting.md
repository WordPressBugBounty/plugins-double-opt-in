# Troubleshooting

## The confirmation email does not arrive

1. Check the spam folder.
2. Under **Double Opt-In → Opt-Ins** set the **Confirmation mail** filter to **Mail failed**. The detail view of an opt-in shows whether the mail was **handed to the mail server** or **could not be sent**, with the error.
3. Could not be sent: the site's mail delivery is the problem. An SMTP plugin that sends through your own mail provider usually fixes it. Then choose **Resend Email** in the opt-in detail.
4. Handed over but not in the inbox: open **Tools → Site Health**. The check "Sender domain of the confirmation mail" reports missing SPF or DMARC records and free-mail senders. The sender in the form settings should be an address on your own domain.
5. While testing, mind the sign-up limit: by default three sign-ups per address and five per IP address per hour (**Settings → Security**).

## The form is not detected

- The free plugin detects Contact Form 7 forms. Contact Form 7 must be active and the form saved.
- Forms from Elementor Pro, WPForms, Gravity Forms and Avada appear with the matching add-on of the Pro bundle. The forms overview points out a missing add-on when the form plugin is installed.

![Site Health: sender domain check](images/en/health-sender-domain.png)

![Site Health: notice about confirmation mails that could not be sent](images/en/health-mail-failures.png)

![Opt-in detail with the delivery status "Could not be sent"](images/en/optin-mail-failed.png)

## A form's switch is locked

At least one required setting is missing: **Recipient field**, **Subject**, or **Message body** or template. The banner in the form names it. A custom text counts only if it contains `[doubleoptinlink]`. A recipient field that no longer exists in the form also counts as missing. After adding it, save and turn the switch on.

## A form was switched off after an update

On update, active forms are checked for completeness and incomplete ones are paused. Add the missing setting, save, and switch the form on again.

## Visitors cannot sign up

- **Consent message:** a **Consent acceptance field** is selected for the form and the checkbox is not ticked. Sign-ups without the checkmark are rejected.
- **Too many sign-ups:** the limits per IP address and email address are reached. The values are under **Settings → Security**.
- Contact Form 7 forms with Double Opt-In contain a hidden field and a minimum fill time of two seconds against bots. A form submitted faster than that is rejected.

![Message when the checkbox is missing](images/en/consent-required.png)

## The confirmation link does not work

| Message | Cause |
|---|---|
| This confirmation link has expired. | The period under **Token Expiry (Hours)** has passed (default 48). The visitor signs up again. |
| Your opt-in has already been confirmed. | A link can be used only once. |
| This confirmation link is invalid. | The record no longer exists, for example after a deletion, or the link was altered. |

The confirmation page controls which texts appear; see "Shortcodes and placeholders".

![Confirmation page for an invalid link](images/en/invalid-link.png)

## The admin screens stay empty or load only partly

Scripts from other plugins or themes can overwrite libraries in the admin. Update the plugin and its add-ons, then reload the page.

![Dashboard notice when many opt-ins stay unconfirmed](images/en/dashboard-unconfirmed.png)

## More help

- The checks under **Tools → Site Health** state the cause and the next step, also for missing database tables and add-on versions that do not match. **Site Health → Info** contains a "Double Opt-In" section that can be attached to a support request.
- A dashboard hint appears when an unusual share of opt-ins stays unconfirmed.
