// Builds includes/data/countries.php from the bundled intl-tel-input data.
// Run after upgrading intl-tel-input: npx -y -p playwright node tools/generate-countries.mjs .
// Bump the version string in the header below too.
import { chromium } from 'playwright';

import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const repo = process.argv[2];

const b = await chromium.launch();
const p = await b.newPage();
await p.setContent('<html><body></body></html>');
await p.addScriptTag({ content: readFileSync(`${repo}/public/js/intlTelInputWithUtils.min.js`, 'utf8') });
const data = await p.evaluate(() => window.intlTelInput.getAllCountries().map((c) => ({ iso2: c.iso2, dial: c.dialCode })));
await b.close();
const dn = new Intl.DisplayNames(['en'], { type: 'region' });
for (const c of data) c.name = dn.of(c.iso2.toUpperCase());

const rows = data
  .filter((c) => c.iso2 !== 'il')
  .sort((a, b) => a.name.localeCompare(b.name, 'en'))
  .map((c) => `    '${c.iso2}' => array( 'name' => '${c.name.replace(/'/g, "\\'")}', 'dial' => '${c.dial}' ),`)
  .join('\n');

const php = `<?php
/**
 * Countries offered by the plugin, keyed by ISO 3166-1 alpha-2 code.
 *
 * Generated from the bundled intl-tel-input ${'29.5.12'} data. Do not edit by hand.
 * Names are English; translate them with __() at display time.
 *
 * @package International_Telephone_Box
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
${rows}
);
`;
mkdirSync(`${repo}/includes/data`, { recursive: true });
writeFileSync(`${repo}/includes/data/countries.php`, php);
console.log('countries:', data.length - 1);
