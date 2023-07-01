# UCRM wFirma plugin

Synchronizes payments and invoices with wFirma.pl system.

## Prerequisites:

- wFirma access key, secret key and app key,
- separate invoice numbering scheme with no checking created in wFirma,
- tax included pricing set in UCRM.

## Installation

1. Clone this repository: `git clone https://github.com/jacekkow/ucrm-wfirma`
2. Run composer: `composer update`
3. Pack the plugin: `./vendor/bin/pack-plugin`
4. Upload wfirma.zip file in UCRM System -> Plugins tab.
5. Click "Add webhook" button next to the "Public URL" field.
6. Configure the plugin.
