// Global chart helpers and UI utilities
window._charts = window._charts || {};

function appBaseUrl() {
	if (typeof window.APP_BASE_URL === 'string' && window.APP_BASE_URL !== '') {
		return window.APP_BASE_URL;
	}
	return '/directorio_empresas';
}

function withBaseUrl(path) {
	var base = appBaseUrl();
	if (path.charAt(0) !== '/') {
		path = '/' + path;
	}
	return base + path;
}

function _loadChartJs(callback) {
	console.debug('[main.js] _loadChartJs called, Chart defined?', typeof Chart !== 'undefined');
	if (typeof Chart !== 'undefined') return callback();
	var s = document.createElement('script');
	s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
	s.onload = function(){ console.debug('[main.js] Chart.js loaded'); callback(); };
	s.onerror = function(e){ console.error('[main.js] Chart.js failed to load', e); };
	document.head.appendChild(s);
}

function initStackedBarChart(options) {
	// options: { canvasId, labels, datasets, height }
	options = options || {};
	var canvasId = options.canvasId || 'categoriesChart';
	var labels = options.labels || [];
	var datasets = options.datasets || [];
	var height = options.height || 240;

	console.debug('[main.js] initStackedBarChart', canvasId, labels.length, datasets.length);
	var canvas = document.getElementById(canvasId);
	if (!canvas) { console.warn('[main.js] canvas not found:', canvasId); return; }
	canvas.style.height = height + 'px';

	_loadChartJs(function(){
		try { if (window._charts[canvasId]) window._charts[canvasId].destroy(); } catch(e){}
		var ctx = canvas.getContext('2d');
		window._charts[canvasId] = new Chart(ctx, {
			type: 'bar',
			data: { labels: labels, datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { tooltip: { mode: 'index', intersect: false }, legend: { position: 'top' } },
				scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
			}
		});
	});
}

// Small helper to create a rounded avatar from image or SVG data URI
function ensureAvatarImages() {
	document.querySelectorAll('img[data-avatar-src]').forEach(function(img){
		if (!img.getAttribute('src') || img.getAttribute('src') === '') {
			img.setAttribute('src', img.dataset.avatarSrc);
		}
	});
}

function initCategoryCarousel(options) {
	options = options || {};
	var containerSelector = options.container || '.category-carousel';
	var prevBtnSelector = options.prevBtn || '.carousel-nav.prev';
	var nextBtnSelector = options.nextBtn || '.carousel-nav.next';
	var step = options.step || 280;

	var container = document.querySelector(containerSelector);
	var prevBtn = document.querySelector(prevBtnSelector);
	var nextBtn = document.querySelector(nextBtnSelector);
	if (!container || !prevBtn || !nextBtn) return;

	// Hide nav if no items
	var items = container.querySelectorAll('.category-carousel-item');
	console.debug('[carousel] items found:', items.length);
	if (!items || items.length === 0) {
		prevBtn.style.display = 'none';
		nextBtn.style.display = 'none';
		return;
	}

	function updateNav() {
		var maxScroll = container.scrollWidth - container.clientWidth;
		var canScroll = maxScroll > 10;
		prevBtn.style.display = canScroll ? 'flex' : 'none';
		nextBtn.style.display = canScroll ? 'flex' : 'none';
		prevBtn.style.opacity = container.scrollLeft > 10 ? '1' : '0.35';
		nextBtn.style.opacity = container.scrollLeft < maxScroll - 10 ? '1' : '0.35';
	}

	function scrollBy(amount) {
		container.scrollBy({ left: amount, behavior: 'smooth' });
	}

	prevBtn.addEventListener('click', function(){ scrollBy(-step); });
	nextBtn.addEventListener('click', function(){ scrollBy(step); });
	container.addEventListener('scroll', updateNav);
	window.addEventListener('resize', updateNav);

	// initial state
	updateNav();
}

function agregarAlCarrito(productoId, nombre, precio, triggerButton) {
    // Crear FormData para enviar los datos
    const formData = new FormData();
    formData.append('producto_id', productoId);
    formData.append('cantidad', 1);

    // Mostrar indicador de carga
	const button = triggerButton || (typeof event !== 'undefined' ? event.target : null);
	const originalText = button ? button.innerHTML : '';
	if (button) {
		button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Agregando...';
		button.disabled = true;
	}

    // Hacer petición AJAX
	fetch(withBaseUrl('/agregar_carrito.php'), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'  // Incluir cookies de sesión
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito
            showToast('Producto agregado al carrito', 'success');
            // Actualizar contador del carrito en el header
			updateCartCounter(data.cart_count);
        } else {
            if (data.redirect) {
                // Usuario no autenticado, redirigir al login
                if (confirm('Para agregar productos al carrito necesitas iniciar sesión. ¿Quieres ir al login?')) {
					window.location.href = withBaseUrl('/' + data.redirect.replace(/^\/+/, ''));
                }
            } else {
                // Otro error
                showToast(data.message || 'Error al agregar producto', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error de conexión', 'error');
    })
    .finally(() => {
        // Restaurar botón
		if (button) {
			button.innerHTML = originalText;
			button.disabled = false;
		}
    });
}

function getCsrfToken() {
	if (typeof window.CSRF_TOKEN === 'string' && window.CSRF_TOKEN !== '') {
		return window.CSRF_TOKEN;
	}
	const meta = document.querySelector('meta[name="csrf-token"]');
	return meta ? meta.getAttribute('content') : '';
}

function parseJsonResponse(response) {
	return response.text().then(function(raw) {
		var payload = null;
		try {
			payload = JSON.parse(raw);
		} catch (e) {
			payload = { success: false, message: 'Respuesta invalida del servidor.' };
		}
		return { ok: response.ok, payload: payload };
	});
}

function setBadgeCount(badgeId, value) {
	var badge = document.getElementById(badgeId);
	if (!badge) return;
	var count = parseInt(value || '0', 10);
	if (Number.isNaN(count) || count < 0) {
		count = 0;
	}
	badge.dataset.count = String(count);
	badge.textContent = String(count);
	if (count > 0) {
		badge.classList.remove('d-none');
	} else {
		badge.classList.add('d-none');
	}
}

function handleProductInteraction(button) {
	if (!button) return;

	const productId = parseInt(button.dataset.productId || '0', 10);
	const action = button.dataset.action || '';

	if (!productId || !action) {
		showToast('Accion invalida.', 'error');
		return;
	}

	const formData = new FormData();
	formData.append('producto_id', productId);
	formData.append('action', action);
	formData.append('csrf_token', getCsrfToken());

	button.disabled = true;

	fetch(withBaseUrl('/user/interacciones_producto.php'), {
		method: 'POST',
		body: formData,
		credentials: 'same-origin'
	})
		.then(parseJsonResponse)
		.then(({ ok, payload }) => {
			if (!ok || !payload.success) {
				if (payload && payload.message && payload.message.toLowerCase().includes('sesion')) {
					window.location.href = withBaseUrl('/auth/login.php');
					return;
				}
				throw new Error((payload && payload.message) || 'No se pudo completar la accion.');
			}

			const isLike = action === 'toggle_like';
			const active = !!payload.active;
			button.classList.toggle(isLike ? 'btn-danger' : 'btn-warning', active);
			button.classList.toggle(isLike ? 'btn-outline-danger' : 'btn-outline-warning', !active);
			button.textContent = isLike ? (active ? 'Quitar me gusta' : 'Me gusta') : (active ? 'Guardado' : 'Guardar');

			const likesEl = document.getElementById('likes-count-' + productId);
			const savesEl = document.getElementById('saves-count-' + productId);
			if (likesEl) likesEl.textContent = payload.likes;
			if (savesEl) savesEl.textContent = payload.guardados;
			setBadgeCount('likes-counter-badge', payload.user_likes || 0);
			setBadgeCount('saved-counter-badge', payload.user_guardados || 0);
		})
		.catch((error) => {
			showToast(error.message || 'No se pudo completar la accion.', 'error');
		})
		.finally(() => {
			button.disabled = false;
		});
}

document.addEventListener('click', function(e) {
	const button = e.target.closest('[data-toggle-product-interaction]');
	if (!button) return;
	e.preventDefault();
	handleProductInteraction(button);
});

function showToast(message, type = 'info') {
    // Crear toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    document.body.appendChild(toast);

    // Auto-remover después de 3 segundos
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 3000);
}

function updateCartCounter(serverCount) {
	if (typeof serverCount !== 'undefined') {
		setBadgeCount('cart-counter-badge', serverCount);
		return;
	}

	var cartBadge = document.getElementById('cart-counter-badge');
	if (!cartBadge) return;
	var currentCount = parseInt(cartBadge.dataset.count || cartBadge.textContent || '0', 10);
	if (Number.isNaN(currentCount)) {
		currentCount = 0;
	}
	setBadgeCount('cart-counter-badge', currentCount + 1);
}

