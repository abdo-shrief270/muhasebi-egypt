// The whole feature guide as one plain-text (markdown) file for AI assistants (prerendered).
export default defineEventHandler((event) => {
  const { siteUrl, appUrl } = useRuntimeConfig().public
  setHeader(event, 'content-type', 'text/plain; charset=utf-8')
  return llmsFull(siteUrl.replace(/\/$/, ''), appUrl.replace(/\/$/, ''))
})
