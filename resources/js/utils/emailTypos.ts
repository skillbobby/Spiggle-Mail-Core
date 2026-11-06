const MISSPELLINGS: Record<string, string[]> = {
  'gmail.com': [
    'gmial.com', 'gmai.com', 'gmal.com', 'gmil.com', 'gmaill.com',
    'gmaul.com', 'gnail.com', 'gamil.com', 'gmali.com', 'gmeil.com',
    'gmsil.com', 'gmaio.com', 'gmaik.com', 'ggmail.com', 'gmmail.com',
    'gmaiil.com', 'gmaail.com', 'gmail.co', 'gmail.cm', 'gmail.om',
  ],
  'googlemail.com': ['googlemai.com', 'googlemial.com', 'googlemail.co'],
  'yahoo.com': [
    'yaho.com', 'yahooo.com', 'yahho.com', 'yhoo.com', 'yhaoo.com',
    'yaoo.com', 'yahool.com', 'uahoo.com', 'yahoo.co', 'yahoo.cm', 'yahoo.om',
  ],
  'hotmail.com': [
    'hotmial.com', 'hotmal.com', 'hotmai.com', 'hotmil.com', 'hotnail.com',
    'hotmali.com', 'homail.com', 'hotamil.com', 'hotmaill.com', 'htomail.com',
    'hormail.com', 'hoymail.com', 'hotmsil.com', 'hotmail.co', 'hotmail.cm', 'hotmail.om',
  ],
  'outlook.com': [
    'outlok.com', 'outllok.com', 'outlool.com', 'oulook.com', 'otlook.com',
    'outook.com', 'putlook.com', 'outlook.co', 'outlook.cm',
  ],
  'icloud.com': [
    'iclod.com', 'icoud.com', 'icluod.com', 'iclould.com', 'icload.com',
    'iclound.com', 'icloud.co', 'icloud.cm',
  ],
};

const MEANT_DOMAINS = new Map<string, string>(
  Object.entries(MISSPELLINGS).flatMap(([domain, typos]) => typos.map((typo) => [typo, domain]))
);

const MEANT_ENDINGS = new Map<string, string>([
  ['con', 'com'], ['cmo', 'com'], ['ocm', 'com'], ['vom', 'com'], ['xom', 'com'],
  ['cpm', 'com'], ['comm', 'com'], ['coom', 'com'], ['ner', 'net'], ['nte', 'net'],
  ['nett', 'net'], ['ogr', 'org'], ['rog', 'org'], ['orgg', 'org'],
]);

function fixEnding(domain: string): string {
  const dot = domain.lastIndexOf('.');
  if (dot === -1) return domain;
  const ending = MEANT_ENDINGS.get(domain.slice(dot + 1));
  return ending ? `${domain.slice(0, dot + 1)}${ending}` : domain;
}

/**
 * Returns a corrected email suggestion if domain typo or common misspelling is detected.
 * e.g. "alex@gmial.com" -> "alex@gmail.com"
 */
export function getEmailTypoSuggestion(email: string): string | null {
  const trimmed = email.trim().toLowerCase();
  const at = trimmed.lastIndexOf('@');
  if (at === -1 || at === 0 || at === trimmed.length - 1) return null;

  const local = trimmed.slice(0, at);
  const domain = trimmed.slice(at + 1);

  const fixed = fixEnding(domain);
  const meant = MEANT_DOMAINS.get(fixed) || (fixed !== domain ? fixed : null);

  if (meant && meant !== domain) {
    return `${local}@${meant}`;
  }

  return null;
}
