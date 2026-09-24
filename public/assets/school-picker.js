function initSchoolPicker(root) {
  const input = root.querySelector('.school-search-input');
  const results = root.querySelector('.school-search-results');
  const selectedList = root.querySelector('.school-selected-list');
  const hiddenContainer = root.querySelector('.school-selected-inputs');
  const excludeIds = new Set((root.dataset.excludeIds || '').split(',').filter(Boolean).map(Number));
  const selected = new Map();

  function renderSelected() {
    selectedList.innerHTML = '';
    hiddenContainer.innerHTML = '';
    if (selected.size === 0) {
      selectedList.innerHTML = '<p class="text-muted small mb-0">ยังไม่ได้เลือกโรงเรียน</p>';
      return;
    }
    selected.forEach((name, id) => {
      const chip = document.createElement('span');
      chip.className = 'badge bg-primary-subtle text-primary-emphasis border me-1 mb-1';
      chip.style.display = 'inline-flex';
      chip.style.alignItems = 'center';
      chip.style.gap = '4px';
      chip.innerHTML = '<span></span><button type="button" class="btn-close btn-close-sm" style="font-size:.6rem;" aria-label="เอาออก"></button>';
      chip.querySelector('span').textContent = name;
      chip.querySelector('button').addEventListener('click', () => {
        selected.delete(id);
        renderSelected();
      });
      selectedList.appendChild(chip);

      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'school_ids[]';
      hidden.value = id;
      hiddenContainer.appendChild(hidden);
    });
  }

  let debounceTimer = null;
  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const q = input.value.trim();
    if (q.length < 2) {
      results.innerHTML = '';
      return;
    }
    debounceTimer = setTimeout(() => {
      fetch('school_search.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(list => {
          results.innerHTML = '';
          list
            .filter(s => !excludeIds.has(s.id) && !selected.has(s.id))
            .forEach(s => {
              const item = document.createElement('button');
              item.type = 'button';
              item.className = 'list-group-item list-group-item-action';
              const loc = [s.school_code, s.district, s.province].filter(Boolean).join(' · ');
              item.innerHTML = '<strong></strong> <span class="text-muted small"></span>';
              item.querySelector('strong').textContent = s.name;
              item.querySelector('span').textContent = loc ? '(' + loc + ')' : '';
              item.addEventListener('click', () => {
                selected.set(s.id, s.name);
                renderSelected();
                results.innerHTML = '';
                input.value = '';
                input.focus();
              });
              results.appendChild(item);
            });
        });
    }, 250);
  });

  renderSelected();
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.school-picker').forEach(initSchoolPicker);
});
