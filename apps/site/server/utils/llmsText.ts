import type { DocBlock } from '../../app/data/docs'
import { docGroups, docs } from '../../app/data/docs'
import { articles } from '../../app/data/articles'
import { faqs, features } from '../../app/data/features'
import { solutions } from '../../app/data/solutions'

/** Guide text without the bits of HTML it uses (<b>, <kbd>). */
const plain = (html: string) => html.replace(/<kbd>(.*?)<\/kbd>/g, '`$1`').replace(/<b>(.*?)<\/b>/g, '**$1**').replace(/<[^>]+>/g, '')

function blockText(b: DocBlock, site: string): string {
  switch (b.t) {
    case 'h': return `### ${b.text}`
    case 'p': return plain(b.text)
    case 'steps': return b.items.map((s, i) => `${i + 1}. ${plain(s)}`).join('\n')
    case 'list': return b.items.map(s => `- ${plain(s)}`).join('\n')
    case 'tip': return `> نصيحة: ${b.text}`
    case 'warn': return `> تنبيه: ${b.text}`
    case 'img': return `![${b.alt}](${site}${b.src})`
  }
}

const intro = (site: string, app: string) => `# محاسبي (Muhasebi)

> برنامج حسابات ومخزون وصيانة لمحلات الموبايلات والإكسسوارات والصيانة في مصر، بالعربي المصري. بيشتغل من المتصفح على الكمبيوتر والموبايل، والكاشير بيكمّل بيع حتى لو النت فصل. تجربة 14 يوم ببلاش، والاشتراك شهري أو سنوي بإنستاباي.

- الموقع: ${site}
- التسجيل: ${app}/register
- الأسعار (بتتحدث من البرنامج نفسه): ${site}/pricing
- اللغة: العربية (مصر)، والأرقام لاتيني.`

export function llmsSummary(site: string, app: string): string {
  const groups = Object.entries(docGroups).map(([key, title]) => `## ${title}\n\n${docs.filter(d => d.group === key).map(d => `- [${d.title}](${site}/docs/${d.slug}): ${d.summary}`).join('\n')}`)
  return `${intro(site, app)}

## المميزات

${features.map(f => `- **${f.title}**: ${f.text}`).join('\n')}

## لكل نوع محل

${solutions.map(x => `- [برنامج ${x.label}](${site}/for/${x.slug}): ${x.description}`).join('\n')}

${groups.join('\n\n')}

## مقالات

${articles.map(a => `- [${a.title}](${site}/blog/${a.slug}): ${a.description}`).join('\n')}

## Optional

- [الشرح كامل في ملف واحد](${site}/llms-full.txt)
- [أسئلة شائعة](${site}/#features)
`
}

export function llmsFull(site: string, app: string): string {
  return `${intro(site, app)}

## المميزات

${features.map(f => `- **${f.title}**: ${f.text}`).join('\n')}

## أسئلة بتتسأل كتير

${faqs.map(f => `**${f.q}**\n${f.a}`).join('\n\n')}

${docs.map(d => `## ${d.title}\n\n${d.summary}\n\nالرابط: ${site}/docs/${d.slug}\n\n${d.blocks.map(b => blockText(b, site)).join('\n\n')}`).join('\n\n---\n\n')}
`
}
