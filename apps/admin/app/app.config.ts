export default defineAppConfig({
  ui: {
    colors: {
      primary: 'teal',
      neutral: 'slate',
    },
    card: {
      slots: {
        root: 'app-card',
      },
    },
    button: {
      slots: {
        base: 'font-bold',
      },
    },
    badge: {
      slots: {
        base: 'rounded-full font-bold',
      },
    },
  },
})
