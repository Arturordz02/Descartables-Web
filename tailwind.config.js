/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './*.html',
    './assets/js/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        // Paleta Editorial Cálida (Páginas Públicas)
        warm: {
          cream: '#FDFBF7',
          sand: '#F4EFEA',
          border: '#EAE3DA',
        },
        terracota: {
          DEFAULT: '#C85A32',
          hover: '#B84A22',
          light: '#FBF0EB',
        },
        amberPeru: {
          DEFAULT: '#D9822B',
          light: '#FDF5EC',
        },
        sageEco: {
          DEFAULT: '#4A5D4E',
          light: '#EDF2EE',
        },
        espresso: {
          DEFAULT: '#1F1815',
          muted: '#574B46',
        },
        // Paleta Formal de Administración (admin.html)
        brand: {
          orange: '#ea580c',
          orangeDark: '#c2410c',
          terracota: '#C85A32',
          amber: '#d97706',
          salvia: '#4A5D4E',
        },
        admin: {
          slateBg: '#090d16',
          slateCard: '#0f172a',
          slateInner: '#162032',
          slateBorder: '#1e293b',
          slateBorderSubtle: '#27354f',
        }
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        heading: ['Outfit', 'sans-serif'],
      }
    }
  },
  safelist: [
    // Patrones de colores dinámicos para insignias y estados
    {
      pattern: /(bg|text|border)-(amber|emerald|blue|rose|purple|stone|orange|green|red|slate|zinc|gray|teal|cyan|indigo|lime)-(50|100|200|300|400|500|600|700|800|900)/,
    },
    // Gradientes dinámicos de categorías (seed data y custom)
    {
      pattern: /(from|to)-(amber|blue|stone|emerald|purple|lime|rose|orange|slate|teal|cyan|indigo|zinc|green)-600\/20/,
    },
    // Badges translúcidos del panel administrativo
    {
      pattern: /bg-(emerald|amber|rose|blue|purple|orange|slate)-500\/10/,
    },
    {
      pattern: /text-(emerald|amber|rose|blue|purple|orange|slate)-400/,
    },
    {
      pattern: /border-(emerald|amber|rose|blue|purple|orange|slate)-500\/20/,
    },
    // Clases explícitas del tema admin
    'bg-admin-slateBg', 'bg-admin-slateCard', 'bg-admin-slateInner',
    'border-admin-slateBorder', 'border-admin-slateBorderSubtle',
    // Estados y transiciones dinámicas comunes en UI JavaScript
    'bg-[#C85A32]', 'bg-[#B84A22]', 'text-[#C85A32]', 'text-[#1F1815]', 'text-[#574B46]',
    'border-[#EAE3DA]', 'bg-[#F4EFEA]', 'bg-[#FDFBF7]', 'bg-[#F8ECE7]',
    'hidden', 'block', 'flex', 'inline-flex', 'grid', 'opacity-50', 'pointer-events-none'
  ],
  plugins: [],
};
