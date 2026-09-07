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
    principal: "+51 900 000 000",
    principal_raw: "51900000000",
    url_principal: "https://wa.me/51900000000",
    secundario: "+51 900 000 002",
    secundario_raw: "51900000002",
    url_secundario: "https://wa.me/51900000002"
  },

  telefonos: {
    central: "(01) 000-0000",
    central_raw: "010000000",
    tel_link: "tel:+5110000000"
  },

  emails: {
    ventas: "ventas@descartablesperuanos.pe",
    cotizaciones: "cotizaciones@descartablesperuanos.pe"
  },

  redes: {
    facebook: "https://facebook.com/descartablesperuanos",
    instagram: "https://instagram.com/descartablesperuanos"
  },

  banners: {
    top: {
      enabled: true,
      texto: "Envíos a todo el Perú por agencias • Atención mayorista directa",
      badge: "Envíos a Todo el Perú",
      link: "catalogo.html"
    },
    hero: {
      badge: "Venta al por Mayor y Menor • Envíos a todo el Perú",
      titulo: "Envases y Descartables para el Sector Gastronómico e Industrial",
      subtitulo: "Abastecemos a restaurantes, pollerías, cafeterías, empresas de catering y distribuidores con productos de primera calidad: Pamolsa, Proplas, cubiertos reforzados y empaques 100% biodegradables.",
      btn_primary_text: "Explorar Catálogo",
      btn_primary_link: "catalogo.html",
      btn_secondary_text: "Asesoría Comercial",
      btn_secondary_link: "contacto.html"
    },
    promo: {
      enabled: true,
      badge: "OFERTA DE TEMPORADA",
      titulo: "Precios Especiales por Cajón y Millar para Restaurantes",
      subtitulo: "Cotiza directamente por volumen y accede a descuentos exclusivos con despacho inmediato a nivel nacional.",
      btn_text: "Solicitar Cotización Mayorista",
      btn_link: "catalogo.html"
    }
  }
};


// Hidratación desde localStorage si ya fue configurada previamente en el Admin
if (typeof localStorage !== 'undefined') {
  try {
    const saved = localStorage.getItem('dp_empresa_config');
    if (saved) {
      const parsed = JSON.parse(saved);
      if (parsed && typeof parsed === 'object') {
        Object.assign(COMPANY_CONTACT, parsed);
      }
    }
  } catch (e) {}
}

if (typeof window !== 'undefined') {
  window.COMPANY_CONTACT = COMPANY_CONTACT;
}


