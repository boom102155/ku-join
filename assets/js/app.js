document.addEventListener('DOMContentLoaded', () => {
  const sweetAlert = window.Swal || window.Sweetalert2;
  const cards = [...document.querySelectorAll('[data-type-card]')];
  const org = document.querySelectorAll('[data-org-field]');
  const status = document.querySelectorAll('[data-status-field]');
  const registrationDetails = document.querySelector('[data-registration-details]');
  const orgInput = document.querySelector('#org_name');
  const statusInput = document.querySelector('#status');
  if (statusInput?.dataset.oldValue) statusInput.value = statusInput.dataset.oldValue;
  function setRegistrationFieldsEnabled(enabled) {
    registrationDetails?.querySelectorAll('input, select, textarea').forEach((field) => {
      field.disabled = !enabled;
    });
  }
  function setType(card) {
    cards.forEach(item => item.classList.remove('is-selected'));
    card.classList.add('is-selected');
    registrationDetails?.classList.remove('is-hidden');
    setRegistrationFieldsEnabled(true);
    const needsOrg = card.dataset.org === '1';
    const needsStatus = card.dataset.status === '1';
    org.forEach(el => el.classList.toggle('is-hidden', !needsOrg));
    status.forEach(el => el.classList.toggle('is-hidden', !needsStatus));
    if (orgInput) orgInput.required = needsOrg;
    if (statusInput) statusInput.required = needsStatus;
  }
  cards.forEach((card) => {
    card.addEventListener('click', () => setType(card));
    card.querySelector('input')?.addEventListener('change', () => setType(card));
  });
  const selected = document.querySelector('[data-type-card] input:checked');
  if (selected) setType(selected.closest('[data-type-card]'));
  else setRegistrationFieldsEnabled(false);

  const selectAllSlots = document.querySelector('[data-select-all-slots]');
  const slotChoices = [...document.querySelectorAll('[data-slot-choice]')];
  const syncSelectAllSlots = () => {
    if (!selectAllSlots || !slotChoices.length) return;
    const selectedCount = slotChoices.filter((slot) => slot.checked).length;
    selectAllSlots.checked = selectedCount === slotChoices.length;
    selectAllSlots.indeterminate = selectedCount > 0 && selectedCount < slotChoices.length;
  };
  selectAllSlots?.addEventListener('change', () => {
    slotChoices.forEach((slot) => { slot.checked = selectAllSlots.checked; });
    selectAllSlots.indeterminate = false;
  });
  slotChoices.forEach((slot) => slot.addEventListener('change', syncSelectAllSlots));
  syncSelectAllSlots();

  const chartCanvas = document.querySelector('[data-registration-chart]');
  const chartSource = document.querySelector('#summary-chart-data');
  if (chartCanvas && chartSource) {
    try {
      const chartData = JSON.parse(chartSource.textContent);
      const drawRegistrationChart = () => {
        const context = chartCanvas.getContext('2d');
        const width = Math.max(320, Math.floor(chartCanvas.getBoundingClientRect().width));
        const height = 340;
        const scale = window.devicePixelRatio || 1;
        chartCanvas.width = width * scale;
        chartCanvas.height = height * scale;
        context.setTransform(scale, 0, 0, scale, 0, 0);
        context.clearRect(0, 0, width, height);

        const labels = chartData.labels || [];
        const datasets = chartData.datasets || [];
        const totals = labels.map((_, index) => datasets.reduce((sum, dataset) => sum + Number(dataset.values[index] || 0), 0));
        const highest = Math.max(1, ...totals);
        const step = Math.max(1, Math.ceil(highest / 5));
        const maximum = step * 5;
        const plot = { left: 42, top: 16, right: 16, bottom: 44 };
        const plotWidth = width - plot.left - plot.right;
        const plotHeight = height - plot.top - plot.bottom;
        const band = plotWidth / Math.max(1, labels.length);
        const barWidth = Math.min(74, band * .62);

        context.font = '500 12px Sarabun, sans-serif';
        context.textAlign = 'right';
        context.textBaseline = 'middle';
        for (let tick = 0; tick <= 5; tick += 1) {
          const value = tick * step;
          const y = plot.top + plotHeight - (value / maximum) * plotHeight;
          context.strokeStyle = 'rgba(23, 73, 61, .13)';
          context.beginPath(); context.moveTo(plot.left, y); context.lineTo(width - plot.right, y); context.stroke();
          context.fillStyle = '#5b716b'; context.fillText(String(value), plot.left - 9, y);
        }

        labels.forEach((label, index) => {
          const x = plot.left + (index * band) + ((band - barWidth) / 2);
          let stack = 0;
          datasets.forEach((dataset) => {
            const value = Number(dataset.values[index] || 0);
            const barHeight = (value / maximum) * plotHeight;
            const y = plot.top + plotHeight - stack - barHeight;
            context.fillStyle = dataset.color;
            context.fillRect(x, y, barWidth, barHeight);
            stack += barHeight;
          });
          context.fillStyle = '#4d625d'; context.textAlign = 'center'; context.textBaseline = 'top';
          context.fillText(label, x + (barWidth / 2), height - plot.bottom + 12);
        });
      };
      drawRegistrationChart();
      if ('ResizeObserver' in window) new ResizeObserver(drawRegistrationChart).observe(chartCanvas);
      else window.addEventListener('resize', drawRegistrationChart);
    } catch (error) {
      chartCanvas.closest('.summary-panel')?.classList.add('has-chart-error');
    }
  }

  const registrationForm = document.querySelector('[data-registration-form]');
  const registrationSubmit = document.querySelector('[data-registration-submit]');
  const registrationLabel = document.querySelector('[data-registration-label]');
  registrationForm?.addEventListener('submit', (event) => {
    const selectedType = registrationForm.querySelector('input[name="participant_type_id"]:checked');
    if (!selectedType) {
      event.preventDefault();
      if (sweetAlert) {
        sweetAlert.fire({
          title: 'เกิดข้อผิดพลาด',
          text: 'กรุณาเลือกประเภทผู้เข้าร่วม',
          icon: 'error',
          confirmButtonText: 'OK',
          confirmButtonColor: '#00775c',
        });
      } else {
        window.alert('กรุณาเลือกประเภทผู้เข้าร่วม');
      }
      return;
    }

    if (!registrationSubmit) return;
    registrationSubmit.disabled = true;
    registrationSubmit.setAttribute('aria-disabled', 'true');
    if (registrationLabel) registrationLabel.textContent = 'กำลังบันทึก…';
  });

  const adminShell = document.querySelector('[data-admin-shell]');
  const adminMenu = document.querySelector('[data-admin-menu]');
  const adminToggle = document.querySelector('[data-admin-menu-toggle]');
  if (adminShell && adminMenu && adminToggle) {
    const mobileQuery = window.matchMedia('(max-width: 760px)');
    const syncAdminMenu = () => {
      const isMobile = mobileQuery.matches;
      const isOpen = adminShell.classList.contains('is-menu-open');
      adminMenu.hidden = isMobile && !isOpen;
      adminToggle.setAttribute('aria-expanded', String(isMobile && isOpen));
    };
    adminToggle.addEventListener('click', () => {
      adminShell.classList.toggle('is-menu-open');
      syncAdminMenu();
    });
    mobileQuery.addEventListener('change', syncAdminMenu);
    syncAdminMenu();
  }

  const listRoot = document.querySelector('[data-participant-list]');
  if (listRoot) {
    const rows = [...listRoot.querySelectorAll('[data-list-row]')];
    const filterButtons = [...listRoot.querySelectorAll('[data-slot-filter]')];
    const searchInput = listRoot.querySelector('[data-list-search]');
    const lengthSelect = listRoot.querySelector('[data-list-length]');
    const status = listRoot.querySelector('[data-list-status]');
    const pagination = listRoot.querySelector('[data-list-pagination]');
    const empty = listRoot.querySelector('[data-list-empty]');
    let activeSlot = 'all';
    let page = 1;

    const renderList = () => {
      const query = (searchInput?.value || '').trim().toLocaleLowerCase('th');
      const pageSize = Number(lengthSelect?.value || 25);
      const filtered = rows.filter((row) => {
        const slotIds = (row.dataset.slotIds || '').split(',');
        const matchesSlot = activeSlot === 'all' || slotIds.includes(activeSlot);
        const matchesQuery = !query || row.textContent.toLocaleLowerCase('th').includes(query);
        return matchesSlot && matchesQuery;
      });
      const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
      page = Math.min(page, pages);
      const from = (page - 1) * pageSize;
      const visible = filtered.slice(from, from + pageSize);
      rows.forEach((row) => { row.hidden = !visible.includes(row); });
      visible.forEach((row, index) => { const number = row.querySelector('[data-row-number]'); if (number) number.textContent = String(from + index + 1); });
      if (empty) empty.hidden = filtered.length !== 0;
      if (status) status.textContent = filtered.length ? `แสดง ${from + 1} ถึง ${Math.min(from + pageSize, filtered.length)} จาก ${filtered.length} แถว` : 'แสดง 0 ถึง 0 จาก 0 แถว';
      if (pagination) {
        pagination.innerHTML = '';
        const addButton = (label, target, disabled = false, current = false) => {
          const button = document.createElement('button');
          button.type = 'button'; button.textContent = label; button.disabled = disabled; button.className = current ? 'is-current' : '';
          button.addEventListener('click', () => { page = target; renderList(); });
          pagination.append(button);
        };
        addButton('ก่อนหน้า', page - 1, page === 1);
        for (let number = 1; number <= pages; number += 1) addButton(String(number), number, false, number === page);
        addButton('ถัดไป', page + 1, page === pages);
      }
    };

    filterButtons.forEach((button) => button.addEventListener('click', () => {
      activeSlot = button.dataset.slotFilter || 'all'; page = 1;
      filterButtons.forEach((item) => {
        const isActive = item === button;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', String(isActive));
      });
      renderList();
    }));
    searchInput?.addEventListener('input', () => { page = 1; renderList(); });
    lengthSelect?.addEventListener('change', () => { page = 1; renderList(); });
    renderList();
  }

  if (sweetAlert) {
    const toast = sweetAlert.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 4200,
      timerProgressBar: true,
    });

    document.querySelectorAll('.toast-success, .toast-error').forEach((notice) => {
      const isError = notice.classList.contains('toast-error');
      const message = notice.textContent.trim();
      notice.remove();
      if (message) toast.fire({ icon: isError ? 'error' : 'success', title: message });
    });

    document.addEventListener('submit', async (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || form.dataset.swalConfirmed === 'true') return;
      const action = form.querySelector('input[name="action"]')?.value;
      if (action !== 'delete') return;

      event.preventDefault();
      event.stopImmediatePropagation();
      const result = await sweetAlert.fire({
        title: 'ยืนยันการลบรายการ?',
        text: 'ข้อมูลที่ลบแล้วไม่สามารถกู้คืนได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบรายการ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#b42318',
      });
      if (result.isConfirmed) {
        form.dataset.swalConfirmed = 'true';
        form.submit();
      }
    }, true);
  }
});
