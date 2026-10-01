// https://llmstxt.org — what the product is, in plain text for AI assistants (prerendered).
export default defineEventHandler((event) => {
  const { siteUrl, appUrl } = useRuntimeConfig().public
  setHeader(event, 'content-type', 'text/plain; charset=utf-8')
  return llmsSummary(siteUrl.replace(/\/$/, ''), appUrl.replace(/\/$/, ''))
})
