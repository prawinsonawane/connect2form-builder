(function() {
	'use strict';

	// Enhance keyboard navigation for Connect2Form.
	document.addEventListener('DOMContentLoaded', function() {
		var forms = document.querySelectorAll('.connect2form-form-wrapper');

		forms.forEach(function(form) {
			// Handle Enter key on buttons.
			form.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' && e.target.type === 'button') {
					e.preventDefault();
					e.target.click();
				}
			});

			// Manage focus for dynamic content.
			form.addEventListener('connect2form:error', function(e) {
				var firstError = form.querySelector('.connect2form-error');
				if (firstError) {
					firstError.focus();
					firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			});

			// Announce form submission status.
			form.addEventListener('connect2form:success', function(e) {
				var announcement = document.getElementById('connect2form-announcements');
				if (announcement) {
					announcement.textContent = connect2formAccessibility.messages.formSubmitted;
				}
			});
		});

		// Enhance select dropdowns.
		var selects = document.querySelectorAll('.connect2form-form-wrapper select');
		selects.forEach(function(select) {
			select.addEventListener('focus', function() {
				this.setAttribute('aria-expanded', 'true');
			});

			select.addEventListener('blur', function() {
				this.setAttribute('aria-expanded', 'false');
			});
		});
	});
})();