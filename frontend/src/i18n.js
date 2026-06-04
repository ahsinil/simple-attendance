import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import id from './locales/id.json'

// Check localStorage for saved language or default to 'id'
const savedLocale = localStorage.getItem('app-language') || 'id'

const i18n = createI18n({
  legacy: false, // Set to false to use Composition API
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: {
    en,
    id
  }
})

export default i18n
