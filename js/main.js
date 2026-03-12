// Global chart helpers and UI utilities
window._charts = window._charts || {};

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

document.addEventListener('DOMContentLoaded', function(){
	ensureAvatarImages();
	initCategoryCarousel({ step: 260 });
	// redraw charts on window resize to keep layout tidy
	var resizeTimer;
	window.addEventListener('resize', function(){
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function(){
			Object.keys(window._charts || {}).forEach(function(k){
				try { window._charts[k].resize(); } catch(e){}
			});
		}, 250);
	});
});

