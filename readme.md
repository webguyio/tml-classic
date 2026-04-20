# TML Classic

Contributors: webguyio, jfarthing84
Donate link: https://webguy.io/donate
Tags: login, registration, lost password, profile, themed
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1
License: GPL
License URI: https://www.gnu.org/licenses/gpl.html

Display themed WordPress login, registration, and lost password forms on any front-end page using a shortcode.

## Description

[💬 Ask Question](https://github.com/webguyio/tml-classic/issues) | [📧 Email Me](mailto:webguywork@gmail.com)

TML Classic lets you display WordPress authentication forms directly within your theme using the `[tml-classic]` shortcode.

**Features**

* Display login, registration, lost password, and reset password forms anywhere via shortcode
* Widget support for sidebars
* Multisite signup form support
* Optional add-ons: Captcha, Email, Links, Moderation, Passwords, Profiles, Redirection, Security

**Captcha Add-on**

Adds captcha verification to login, registration, and lost password forms. Supports reCAPTCHA v2, hCaptcha, and Cloudflare Turnstile.

**Email Add-on**

Adds the ability to disable admin emails and customize user emails.

**Links Add-on**

Adds the ability to show custom dashboard links in the TML Classic widget.

**Moderation Add-on**

Adds the ability to approve/deny new pending users.

**Passwords Add-on**

Adds password fields to the registration form. No additional settings required.

**Profiles Add-on**

Adds the ability to block admin access and enable themed user settings on the front-end based on user role.

**Redirection Add-on**

Adds the ability to redirect users to custom areas when logging in/out based on user role.

**Security Add-on**

Adds security options like disabling wp-login.php, brute-force protection, and making the site private.

## Installation

1. Upload the `tml-classic` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Configure settings under **TML Classic**.

## Frequently Asked Questions

### Why does this fork exist?

Theme My Login 6.x was a popular, straightforward approach to themed login forms that many sites relied on. When version 7 arrived it was a significant rewrite with a different approach, and the 6.x branch was retired. Over time, the original 6.4.17 release developed compatibility issues with newer versions of WordPress and PHP. TML Classic picks up where 6.4.17 left off, keeping the classic approach working on modern installs.

### How do I display the login form?

Add the `[tml-classic]` shortcode to any page. The form displayed will match the current action (login, register, lost password, etc.).

### Can I show a specific form?

Yes. Use `[tml-classic action="login"]`, `[tml-classic action="register"]`, `[tml-classic action="lostpassword"]`, etc.

### Does this work with multisite?

Yes. Multisite signup forms are supported. The Moderation add-on is not available on multisite.

## Changelog

### 0.1
* Fork of old Theme My Login branch ([6.4.17](https://downloads.wordpress.org/plugin/theme-my-login.6.4.17.zip)) by Jeff Farthing (@jfarthing84)