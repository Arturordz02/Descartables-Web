/**
 * ==============================================================================
 * CONFIGURACIÓN CENTRALIZADA DE CONTACTO Y ATENCIÓN AL CLIENTE
 * Descartables Peruanos S.A.C.
 * ==============================================================================
 * 
 * Para reactivar las redirecciones automáticas a WhatsApp o llamadas directas,
 * cambia ENABLE_REDIRECTS a `true`.
 * 
 * Para actualizar números de teléfono, correos o direcciones, edita este archivo.
 */

const COMPANY_CONTACT = {
  // Estado de enlaces activos (false = deshabilitado / solo informativo, true = activo)
  ENABLE_REDIRECTS: false,

  empresa: {
    razon_social: "DESCARTABLES PERUANOS S.A.C.",
    nombre_comercial: "Descartables Peruanos",
    ruc: "20601234567",
    direccion: "Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú",
    horario: "Lunes a Viernes: 8:00 AM - 6:00 PM | Sábados: 8:30 AM - 1:00 PM"
  },

  whatsapp: {
    principal: "+51 994 195 430",
    principal_raw: "51994195430",
    url_principal: "https://wa.me/51994195430",
    secundario: "+51 994 009 692",
    secundario_raw: "51994009692",
    url_secundario: "https://wa.me/51994009692"
  },

  telefonos: {
    central: "(01) 564-1450",
    central_raw: "015641450",
    tel_link: "tel:+5115641450"
  },

  emails: {
    ventas: "ventas@descartablesperuanos.pe",
    cotizaciones: "cotizaciones@descartablesperuanos.pe"
  },

  redes: {
    facebook: "https://facebook.com/descartablesperuanos",
    instagram: "https://instagram.com/descartablesperuanos"
  }
};

if (typeof window !== 'undefined') {
  window.COMPANY_CONTACT = COMPANY_CONTACT;
}
