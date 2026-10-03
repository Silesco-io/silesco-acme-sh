// SPDX-License-Identifier: Apache-2.0
// Offline build-time metadata reader: never sources or evaluates upstream shell.
import {readFileSync, readdirSync, writeFileSync, existsSync} from 'node:fs';
import {join, resolve} from 'node:path';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';

const root = resolve(import.meta.dirname, '..');
const source = process.argv[2];
if (!source) throw new Error('usage: node scripts/catalog.mjs PINNED_SOURCE_DIRECTORY [--check]');
const hash = data => createHash('sha256').update(data).digest('hex');
const readJson = path => JSON.parse(readFileSync(path, 'utf8'));
const upstream = readJson(join(root, 'UPSTREAM.json'));
if (!/^\d+\.\d+\.\d+$/.test(upstream.release) || !/^[a-f0-9]{40}$/.test(upstream.commit)) throw new Error('upstream.pin_invalid');
const script = readFileSync(join(source, 'acme.sh'));
if (!script.toString('utf8').includes(`VER=${upstream.release}`)) throw new Error('upstream.version_mismatch');
if (!upstream.acmeScriptSha256 || hash(script) !== upstream.acmeScriptSha256) throw new Error('upstream.script_mismatch');
if (existsSync(join(source, '.git'))) {
  const revision = execFileSync('git', ['-C', source, 'rev-parse', 'HEAD'], {encoding:'utf8'}).trim();
  if (revision !== upstream.commit) throw new Error('upstream.commit_mismatch');
}
const overrides = readJson(join(root, 'resources/provider-overrides.json'));
const overrideMap = overrides.providers ?? overrides;
const messages = {en:{}, ru:{}};
const message = (key, en, ru, description='Provider setup metadata') => {
  messages.en[key] = {message:en, description, placeholders:{}};
  messages.ru[key] = {message:ru, description, placeholders:{}};
  return key;
};
const url = value => {
  if (!value) return undefined;
  const candidate = value.startsWith('https://') ? value : `https://${value}`;
  if (!/^https:\/\/[a-zA-Z0-9.-]+(?::\d+)?(?:[/?#][^\s]*)?$/.test(candidate)) throw new Error(`catalog.url_invalid:${value}`);
  return candidate;
};

/** Read only the explicit published _info data assignment, not executable code. */
export function parseInfo(text, id) {
  const data = text.match(new RegExp(`^${id}_info='([^']*)'`, 'm'))?.[1];
  if (!data) throw new Error(`catalog.metadata_missing:${id}`);
  if (data.trimStart().startsWith('[')) {
    const fields = JSON.parse(data).map(field => ({name:field.name, description:field.usage, required:field.required === '1', sourceSection:'json-info'}));
    return {displayName:id, metadataHash:hash(data), sections:[{id:'default', fields}], urls:[]};
  }
  const lines = data.split(/\r?\n/);
  const result = {displayName:lines[0].trim(), metadataHash:hash(data), sections:[], urls:[]};
  let section;
  for (const line of lines.slice(1)) {
    const heading = line.match(/^([A-Za-z]+):\s*(.*)$/);
    if (heading) {
      if (heading[1] === 'Options' || heading[1] === 'OptionsAlt') {
        section = {id:heading[1] === 'Options' ? 'default' : 'alternative', sourceSection:heading[1], fields:[]};
        result.sections.push(section);
      } else if (heading[1] === 'Optional') {
        // This heading supplements the active variant (Hetzner Cloud metadata).
        if (!section) throw new Error(`catalog.optional_without_variant:${id}`);
        section.optionalFields = true;
      } else {
        section = undefined;
        if (heading[1] === 'Docs') result.documentationUrl = url(heading[2]);
        if (heading[1] === 'Site') result.siteUrl = url(heading[2]);
      }
      continue;
    }
    if (!section) continue;
    const match = line.trim().match(/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(.*))?$/);
    // Sentences in Options are not environment variables.
    if (!match || !/[A-Z_]/.test(match[1]) || match[1] === 'Get') continue;
    section.fields.push({name:match[1], description:match[2] ?? '', sourceSection:section.optionalFields ? 'Optional' : section.sourceSection,
      ...(section.optionalFields ? {required:false} : {})});
  }
  // Do not mistake an example API endpoint for a credential creation page.
  for (const line of lines.filter(line=> /get.*from|created.*at|control panel.*at|account page.*at|dashboard.*at/i.test(line))) {
    for (const found of line.matchAll(/https:\/\/[^\s"<>]+/g)) result.urls.push(found[0].replace(/[).,;]+$/, ''));
  }
  return result;
}

/** Only explicit optional/default metadata affects inferred requiredness. */
export function requirement(field) {
  const description = field.description ?? '';
  let defaultValue = field.default ?? description.match(/(?:^|[.(,])\s*defaults?\s*(?:to|:)?\s*["“]([^"”]*)["”]/i)?.[1];
  if (defaultValue === undefined) defaultValue = description.match(/(?:^|[.(,])\s*default\s*:?\s+(https?:\/\/[^\s)".]+(?:\.[^\s)".]+)*|[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*)(?=[).,;]|$|\s+seconds?[).,;])/i)?.[1];
  const optional = /\boptional(?:ly)?\b|\boverride\b/i.test(description) || defaultValue !== undefined;
  const conditional = /(?:required only|only required|only needed|only used|instead of)/i.test(description);
  const result = {name:field.name, required:field.required ?? !(optional || conditional)};
  if (defaultValue !== undefined) result.default = defaultValue;
  // Conditional semantics must be supplied as typed rules in a reviewed override.
  if (field.condition) result.condition = field.condition;
  else if (conditional && field.required === undefined) result.metadataConditional = true;
  return result;
}

function fieldKind(name, description) {
  const value = `${name} ${description}`;
  if (/password|secret|token|api.?key|access.?key|auth.?data|credentials|tsig|private.?key|sha256|wapipass|\bPWD\b|\bPASS\b/i.test(value)) return ['Учётные данные','Credential',true];
  if (/url|endpoint|hostname|server|\bhost\b|\bapi_base\b/i.test(value)) return ['Адрес сервиса','Service address',false];
  if (/\bttl\b|lifetime|expire|expiration/i.test(value)) return ['Время действия','Lifetime',false];
  if (/email/i.test(value)) return ['Адрес электронной почты','Email address',true];
  if (/username|\buser\b|login|account name/i.test(value)) return ['Логин','Account login',true];
  if (/port/i.test(value)) return ['Порт','Port',false];
  if (/zone|subdomain|root.domain|domainname/i.test(value)) return ['DNS-зона','DNS zone',false];
  if (/region|location/i.test(value)) return ['Регион','Region',false];
  if (/file|path/i.test(value)) return ['Путь к файлу','File path',true];
  if (/insecure|true.*false|managed.?identity|private.?zone/i.test(value)) return ['Режим работы','Operation mode',false];
  if (/\bid\b|identifier|project|customer|prefix|organization/i.test(value)) return ['Идентификатор','Identifier',false];
  return ['Параметр','Setting',true]; // Unknown values stay conservatively sensitive.
}

const providers = {};
const driverFiles = readdirSync(join(source, 'dnsapi')).filter(file => /^dns_[a-zA-Z0-9_]+\.sh$/.test(file)).sort();
const inventory = driverFiles.map(file=>`${hash(readFileSync(join(source,'dnsapi',file)))}  ${file}\n`).join('');
if (!upstream.dnsInventorySha256 || hash(inventory)!==upstream.dnsInventorySha256 || driverFiles.length!==upstream.dnsDriverCount) throw new Error('upstream.driver_inventory_mismatch');
for (const file of driverFiles) {
  const bytes = readFileSync(join(source, 'dnsapi', file));
  const id = file.slice(0, -3);
  const parsed = parseInfo(bytes.toString('utf8'), id);
  const override = overrideMap[id] ?? {};
  const fieldMap = new Map();
  let duplicates = false;
  if (!override.replaceFields) for (const section of parsed.sections) for (const field of section.fields) {
    if (fieldMap.has(field.name) && fieldMap.get(field.name).sourceSection === field.sourceSection) duplicates = true;
    fieldMap.set(field.name, fieldMap.get(field.name) ?? field);
  }
  if (duplicates && !override.fields) throw new Error(`catalog.duplicate_metadata:${id}`);
  for (const field of override.fields ?? []) fieldMap.set(field.name, {...fieldMap.get(field.name), ...field});
  const sections = override.authVariants ?? (parsed.sections.length ? parsed.sections.map(section => ({id:section.id,
    fields:section.fields.map(field=>requirement({...field,...(override.fields ?? []).find(own=>own.name===field.name)}))})) : [{id:'default', fields:[]}]);
  if (parsed.sections.length === 0 && !override.authVariants) throw new Error(`catalog.prerequisite_review_missing:${id}`);
  const variants = sections.map((section, index) => ({id:section.id,
    labelKey:message(`provider.${id}.variant.${section.id}`, section.labelEn ?? (index === 0 ? 'Standard configuration' : 'Alternative configuration'), section.labelRu ?? (index === 0 ? 'Основная настройка' : 'Альтернативная настройка')),
    fields:section.fields.map(field => {
      if (!fieldMap.has(field.name)) throw new Error(`catalog.variant_field_unknown:${id}:${field.name}`);
      return {...field};
    })}));
  const displayName = override.displayName ?? parsed.displayName;
  const provider = {displayName, scriptSha256:hash(bytes), metadataSha256:parsed.metadataHash,
    sourceUrl:`https://github.com/acmesh-official/acme.sh/blob/${upstream.commit}/dnsapi/${file}`,
    documentationUrl:override.documentationUrl ?? parsed.documentationUrl ?? 'https://github.com/acmesh-official/acme.sh/wiki/dnsapi',
    formCoverage:Object.keys(override).length ? 'reviewed_override' : 'upstream_metadata', accountTested:false,
    helpKey:message(`provider.${id}.help`, override.help?.en ?? `Use the linked documentation to enable DNS API access for ${displayName}. acme.sh creates and removes the challenge TXT records. This form describes the pinned release; it does not verify your account.`, override.help?.ru ?? `По ссылке на документацию настройте доступ к DNS API ${displayName}. acme.sh создаёт и удаляет проверочные TXT-записи. Форма соответствует закреплённой версии, но не подтверждает доступ к вашей учётной записи.`),
    fields:[], authVariants:variants,
    prerequisites:(override.prerequisites ?? []).map((entry, index) => ({helpKey:message(`provider.${id}.prerequisite.${index}`, entry.en, entry.ru)})),
  };
  if (parsed.siteUrl) provider.siteUrl = parsed.siteUrl;
  if (override.credentialsUrl) provider.credentialsUrl = url(override.credentialsUrl);
  else provider.credentialsUrl = parsed.urls[0] ? url(parsed.urls[0]) : provider.documentationUrl;
  for (const field of fieldMap.values()) {
    const [ruLabel,enLabel,secret] = fieldKind(field.name, field.description ?? '');
    const first = variants[0].fields.find(item => item.name === field.name);
    const own = (override.fields ?? []).find(item => item.name === field.name) ?? {};
    const key = `provider.${id}.field.${field.name}`;
    provider.fields.push({name:field.name, required:first?.required ?? false,
      secret:own.secret ?? secret, type:own.type ?? ((own.secret ?? secret) ? 'password' : 'text'), maxLength:own.maxLength ?? 4096,
      labelKey:message(`${key}.label`, own.labelEn ?? `${enLabel} (${field.name})`, own.labelRu ?? `${ruLabel} (${field.name})`),
      helpKey:message(`${key}.help`, own.descriptionEn ?? `Value of ${field.name} in the ${displayName} configuration. The linked provider guide explains the accepted format and account permissions.`, own.descriptionRu ?? `Значение ${field.name} для настройки ${displayName}. Допустимый формат и необходимые права учётной записи описаны в инструкции провайдера.`),
      sourceSection:field.sourceSection ?? 'reviewed_override',
      ...(first?.default !== undefined ? {default:first.default} : {}),
      ...(first?.condition !== undefined ? {condition:first.condition} : {}),
    });
  }
  providers[id] = provider;
}
for (const id of Object.keys(overrideMap)) if (!providers[id]) throw new Error(`catalog.override_unknown:${id}`);
const catalog = {schemaVersion:2, upstream, providers};
const outputs = new Map([[join(root,'resources/providers.json'), JSON.stringify(catalog,null,2)+'\n']]);
for (const locale of ['en','ru']) outputs.set(join(root,`locales/${locale}.json`), JSON.stringify({schemaVersion:1,component:'silesco-acme-sh',locale,messages:messages[locale]},null,2)+'\n');
for (const [path, output] of outputs) {
  if (process.argv.includes('--check')) {
    if (readFileSync(path,'utf8') !== output) throw new Error(`catalog.not_reproducible:${path}`);
  } else writeFileSync(path, output);
}
console.log(`${Object.keys(providers).length} pinned DNS drivers; ${Object.values(providers).filter(provider=>provider.formCoverage==='reviewed_override').length} reviewed metadata overrides; 0 live account tests`);
