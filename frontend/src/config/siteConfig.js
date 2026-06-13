// Static brand/contact defaults for the public site.
// These can be overridden later by a public settings API (useSiteConfig).

export const siteConfig = {
  brand: {
    name_bn: 'পড়ালেখা',
    name_en: 'Poralekha',
    tagline_bn: 'শেখা হোক আনন্দে',
    tagline_en: 'Learning, made joyful',
  },
  contact: {
    phone: '+8809610000000',
    phoneDisplay: '09610-000000',
    hotline: '16910',
    whatsapp: '+8801700000000',
    email: 'info@poralekha.com',
    hours_bn: 'প্রতিদিন সকাল ৯টা – রাত ৯টা',
    hours_en: 'Daily 9:00 AM – 9:00 PM',
  },
  address: {
    line_bn: 'হাউজ ১২, রোড ৫, ধানমন্ডি, ঢাকা ১২০৫',
    line_en: 'House 12, Road 5, Dhanmondi, Dhaka 1205',
    mapEmbed: 'https://www.google.com/maps?q=Dhanmondi,Dhaka&output=embed',
  },
  social: {
    facebook: 'https://facebook.com',
    youtube: 'https://youtube.com',
    instagram: 'https://instagram.com',
    linkedin: 'https://linkedin.com',
  },
}

export const whatsappLink = (text = '') => {
  const num = siteConfig.contact.whatsapp.replace(/[^0-9]/g, '')
  const q = text ? `?text=${encodeURIComponent(text)}` : ''
  return `https://wa.me/${num}${q}`
}

export const telLink = () => `tel:${siteConfig.contact.phone.replace(/[^0-9+]/g, '')}`
export const mailtoLink = () => `mailto:${siteConfig.contact.email}`
