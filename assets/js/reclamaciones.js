/**
 * Módulo del Libro de Reclamaciones Virtual
 * Normativa Legal INDECOPI (D.S. 011-2011-PCM / Ley N° 31435)
 */

const Reclamaciones = {
  empresa: {
    razon_social: "DESCARTABLES PERUANOS S.A.C.",
    ruc: "20601234567",
    direccion: "Av. Alejandro Bertello 732-C, Cercado de Lima",
    telefono: "(01) 564-1450",
    email: "reclamaciones@descartablesperuanos.pe"
  },

  init() {
    this.populateGeographicSelects();
    this.attachFormListeners();
    this.injectHojaOficialModal();
  },

  populateGeographicSelects() {
    const depSelect = document.getElementById('recDepartamento');
    const distSelect = document.getElementById('recDistrito');

    if (depSelect && typeof DEPARTAMENTOS_PERU !== 'undefined') {
      depSelect.innerHTML = DEPARTAMENTOS_PERU.map(dep => `
        <option value="${dep}" ${dep === 'Lima' ? 'selected' : ''}>${dep}</option>
      `).join('');
    }

    if (distSelect && typeof DISTRITOS_LIMA !== 'undefined') {
      distSelect.innerHTML = DISTRITOS_LIMA.map(dist => `
        <option value="${dist}" ${dist === 'Cercado de Lima' ? 'selected' : ''}>${dist}</option>
      `).join('');
    }
  },

  attachFormListeners() {
    const form = document.getElementById('formLibroReclamaciones');
    if (!form) return;

    // Toggle menor de edad
    const esMenorCheckbox = document.getElementById('recEsMenor');
    const tutorContainer = document.getElementById('tutorContainer');
    if (esMenorCheckbox && tutorContainer) {
      esMenorCheckbox.addEventListener('change', (e) => {
        if (e.target.checked) {
          tutorContainer.classList.remove('hidden');
          document.getElementById('recNombreTutor').setAttribute('required', 'required');
        } else {
          tutorContainer.classList.add('hidden');
          document.getElementById('recNombreTutor').removeAttribute('required');
        }
      });
    }

    // Validación interactiva de documento
    const tipoDoc = document.getElementById('recTipoDoc');
    const numDoc = document.getElementById('recNumDoc');
    if (tipoDoc && numDoc) {
      tipoDoc.addEventListener('change', () => {
        if (tipoDoc.value === 'DNI') {
          numDoc.maxLength = 8;
          numDoc.placeholder = '8 dígitos numéricos';
        } else if (tipoDoc.value === 'RUC') {
          numDoc.maxLength = 11;
          numDoc.placeholder = '11 dígitos numéricos';
        } else {
          numDoc.maxLength = 12;
          numDoc.placeholder = 'N° de documento';
        }
      });
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleFormSubmit(form);
    });
  },

  async handleFormSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const tipoDoc = document.getElementById('recTipoDoc').value;
    const numDoc = document.getElementById('recNumDoc').value.trim();
    const nombre = document.getElementById('recNombre').value.trim();
    const tel = document.getElementById('recTelefono').value.trim();
    const email = document.getElementById('recEmail').value.trim();
    const dep = document.getElementById('recDepartamento').value;
    const prov = document.getElementById('recProvincia').value.trim() || 'Lima';
    const dist = document.getElementById('recDistrito').value;
    const dir = document.getElementById('recDireccion').value.trim();

    const esMenor = document.getElementById('recEsMenor')?.checked || false;
    const nombreTutor = esMenor ? document.getElementById('recNombreTutor').value.trim() : null;

    const tipoBien = document.querySelector('input[name="tipo_bien"]:checked')?.value || 'Producto';
    const monto = parseFloat(document.getElementById('recMonto').value) || 0.00;
    const descBien = document.getElementById('recDescBien').value.trim();

    const tipoReclamacion = document.querySelector('input[name="tipo_reclamacion"]:checked')?.value || 'Reclamo';
    const detalleRec = document.getElementById('recDetalle').value.trim();
    const pedidoCon = document.getElementById('recPedido').value.trim();

    // Validaciones legales
    if (tipoDoc === 'DNI' && numDoc.length !== 8) {
      alert('El DNI debe contener exactamente 8 dígitos según RENIEC.');
      return;
    }
    if (tipoDoc === 'RUC' && numDoc.length !== 11) {
      alert('El RUC debe contener exactamente 11 dígitos según SUNAT.');
      return;
    }
    if (esMenor && !nombreTutor) {
      alert('Debe ingresar los nombres del Padre, Madre o Tutor si el consumidor es menor de edad.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
      <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
      <span>Generando Hoja de Reclamación...</span>
    `;

    const payload = {
      tipo_documento: tipoDoc,
      numero_documento: numDoc,
      nombre_completo: nombre,
      telefono: tel,
      email: email,
      departamento: dep,
      provincia: prov,
      distrito: dist,
      direccion: dir,
      es_menor: esMenor ? 1 : 0,
      nombre_tutor: nombreTutor,
      tipo_bien: tipoBien,
      monto_reclamado: monto,
      descripcion_bien: descBien,
      tipo_reclamacion: tipoReclamacion,
      detalle_reclamacion: detalleRec,
      pedido_consumidor: pedidoCon
    };

    const res = await ApiService.registerReclamacion(payload);

    submitBtn.disabled = false;
    submitBtn.innerHTML = `
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <span>Registrar Hoja de Reclamación</span>
    `;

    if (res.success) {
      form.reset();
      this.openHojaOficialModal({
        ...payload,
        codigo_hoja: res.codigo_hoja,
        fecha: res.fecha || new Date().toLocaleString('es-PE'),
        empresa: this.empresa
      });
    } else {
      alert('No se pudo registrar la reclamación: ' + (res.error || 'Intente nuevamente.'));
    }
  },

  injectHojaOficialModal() {
    if (document.getElementById('modalHojaReclamacion')) return;

    const modalHTML = `
      <div id="modalHojaReclamacion" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-stone-900/70 backdrop-blur-md hidden animate-fade-in overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[94vh] overflow-y-auto shadow-2xl border border-stone-300 relative my-4 sm:my-8 touch-scroll">
          
          <!-- Barra de Acciones Superior Responsiva -->
          <div class="no-print sticky top-0 z-20 bg-[#FDFBF7] px-4 sm:px-6 py-3 sm:py-4 border-b border-[#EAE3DA] flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="flex items-center gap-2 text-emerald-700 font-bold text-xs sm:text-sm">
              <svg class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>Hoja Registrada con Éxito</span>
            </div>
            
            <div class="flex items-center justify-end gap-2">
              <button onclick="window.print()" class="allow-print px-3 sm:px-4 py-2 bg-stone-800 hover:bg-stone-900 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm tap-target">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Imprimir / PDF</span>
              </button>
              <button onclick="Reclamaciones.closeHojaOficialModal()" class="p-2 text-stone-500 hover:text-stone-800 rounded-xl hover:bg-stone-100 tap-target" aria-label="Cerrar ventana">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>

          <!-- ÁREA IMPRIMIBLE OFICIAL INDECOPI -->
          <div id="hojaReclamacionPrintArea" class="p-4 sm:p-6 md:p-10 font-sans text-[#1F1815] text-xs leading-normal">
            <!-- Renderizado dinámico -->
          </div>

        </div>
      </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = modalHTML;
    document.body.appendChild(div.firstElementChild);
  },

  openHojaOficialModal(data) {
    const modal = document.getElementById('modalHojaReclamacion');
    const container = document.getElementById('hojaReclamacionPrintArea');
    if (!modal || !container) return;

    container.innerHTML = `
      <div class="hoja-print-border border-2 border-stone-800 p-4 sm:p-6 rounded-2xl bg-white space-y-4 sm:space-y-5">
        
        <!-- Encabezado Oficial -->
        <div class="flex flex-col sm:flex-row justify-between items-start border-b-2 border-stone-800 pb-4 gap-3 sm:gap-4">
          <div>
            <span class="inline-block px-2.5 py-1 bg-amber-100 text-amber-900 font-bold text-[9px] sm:text-[10px] rounded uppercase tracking-wider mb-1">
              Reglamento del Libro de Reclamaciones (D.S. 011-2011-PCM)
            </span>
            <h2 class="text-base sm:text-lg font-black text-[#1F1815] uppercase tracking-wide">LIBRO DE RECLAMACIONES VIRTUAL</h2>
            <p class="font-bold text-xs sm:text-sm text-[#C85A32]">${this.empresa.razon_social}</p>
            <p class="text-[10px] sm:text-[11px] text-stone-600">RUC: ${this.empresa.ruc} | Dirección: ${this.empresa.direccion}</p>
          </div>

          <div class="bg-stone-50 p-2.5 sm:p-3 rounded-xl border border-stone-300 w-full sm:w-auto text-left sm:text-right sm:min-w-[190px]">
            <p class="text-[10px] text-stone-500 uppercase font-semibold">Hoja de Reclamación N°</p>
            <p class="font-mono text-base font-black text-rose-700 tracking-wider">${data.codigo_hoja}</p>
            <p class="text-[10px] sm:text-[11px] text-stone-600 mt-0.5"><strong>Fecha:</strong> ${data.fecha}</p>
          </div>
        </div>

        <!-- 1. Identificación del Consumidor Reclamante -->
        <div class="border border-stone-300 rounded-xl p-3.5 bg-stone-50/50">
          <h4 class="font-bold text-[11px] uppercase tracking-wider text-stone-800 border-b border-stone-200 pb-1 mb-2">
            1. Identificación del Consumidor Reclamante
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
            <div><strong>Nombres / Razón Social:</strong> ${data.nombre_completo}</div>
            <div><strong>Documento (${data.tipo_documento}):</strong> ${data.numero_documento}</div>
            <div><strong>Teléfono:</strong> ${data.telefono}</div>
            <div><strong>Correo Electrónico:</strong> ${data.email}</div>
            <div class="sm:col-span-2"><strong>Domicilio:</strong> ${data.direccion}, ${data.distrito}, ${data.provincia}, ${data.departamento}</div>
            ${data.es_menor ? `<div class="sm:col-span-2 text-amber-800"><strong>Padre / Madre o Tutor:</strong> ${data.nombre_tutor}</div>` : ''}
          </div>
        </div>

        <!-- 2. Identificación del Bien Contratado -->
        <div class="border border-stone-300 rounded-xl p-3.5 bg-stone-50/50">
          <h4 class="font-bold text-[11px] uppercase tracking-wider text-stone-800 border-b border-stone-200 pb-1 mb-2">
            2. Identificación del Bien Contratado
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
            <div><strong>Tipo de Bien:</strong> ${data.tipo_bien}</div>
            <div><strong>Monto Reclamado:</strong> S/ ${parseFloat(data.monto_reclamado).toFixed(2)} Soles</div>
            <div class="sm:col-span-2"><strong>Descripción del Bien:</strong> ${data.descripcion_bien}</div>
          </div>
        </div>

        <!-- 3. Detalle de la Reclamación y Pedido -->
        <div class="border border-stone-300 rounded-xl p-3.5 bg-stone-50/50">
          <div class="flex items-center justify-between border-b border-stone-200 pb-1 mb-2">
            <h4 class="font-bold text-[11px] uppercase tracking-wider text-stone-800">
              3. Detalle de la Reclamación
            </h4>
            <span class="px-2 py-0.5 rounded font-bold text-[10px] ${data.tipo_reclamacion === 'Reclamo' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'}">
              Tipo: ${data.tipo_reclamacion.toUpperCase()}
            </span>
          </div>
          
          <div class="space-y-3 text-[11px]">
            <div>
              <p class="font-semibold text-stone-700">Detalle:</p>
              <p class="p-2 bg-white rounded border border-stone-200 mt-1 whitespace-pre-wrap">${data.detalle_reclamacion}</p>
            </div>
            <div>
              <p class="font-semibold text-stone-700">Pedido Concreto del Consumidor:</p>
              <p class="p-2 bg-white rounded border border-stone-200 mt-1 whitespace-pre-wrap">${data.pedido_consumidor}</p>
            </div>
          </div>
        </div>

        <!-- Marco Legal y Plazos Ley 31435 -->
        <div class="text-[10px] text-stone-500 border-t border-stone-200 pt-3 space-y-1">
          <p><strong>* RECLAMO:</strong> Disconformidad relacionada a los productos o servicios ofrecidos o comercializados.</p>
          <p><strong>* QUEJA:</strong> Malestar o descontento respecto a la atención al público.</p>
          <p class="font-semibold text-stone-700">Plazo legal de respuesta: Conforme a la Ley N° 31435, la empresa brindará respuesta en un plazo no mayor a 15 (quince) días hábiles improrrogables.</p>
        </div>

      </div>
    `;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  },

  closeHojaOficialModal() {
    const modal = document.getElementById('modalHojaReclamacion');
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    }
  }
};

window.Reclamaciones = Reclamaciones;

