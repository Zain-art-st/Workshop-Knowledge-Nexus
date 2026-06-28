<div id="reportModal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);
            backdrop-filter:blur(6px);z-index:3000;
            align-items:center;justify-content:center;">
  <div style="background:#1e1c35;border:1px solid rgba(255,255,255,.12);
              border-radius:18px;padding:28px;width:100%;max-width:420px;
              margin:20px;box-shadow:0 16px 48px rgba(0,0,0,.6);">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:#f0eff5;">
        🚩 Report
      </h3>
      <button onclick="closeReportModal()"
              style="background:none;border:none;color:#9b9ab0;font-size:20px;
                     cursor:pointer;line-height:1;padding:4px;"
              onmouseover="this.style.color='#ff4f6a'"
              onmouseout="this.style.color='#9b9ab0'">✕</button>
    </div>

    <p style="font-size:13px;color:#9b9ab0;margin-bottom:16px;line-height:1.6;">
      Why are you reporting this? Your report is anonymous and will be reviewed by an admin.
    </p>

    <!-- Reason options -->
    <div id="reportReasons" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
      <?php
      $reasons = [
        ['label'=>'Unwanted commercial content or spam'],
        ['label'=>'Pornography or sexually explicit material'],
        ['label'=>'Hate speech or graphic violence'],
        ['label'=>'Harassment or bullying'],
        ['label'=>'Misinformation'],
      ];
      foreach ($reasons as $r):
      ?>
      <label style="display:flex;align-items:center;gap:12px;padding:11px 14px;
                    background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
                    border-radius:10px;cursor:pointer;transition:all .2s;"
             onmouseover="this.style.borderColor='rgba(79,142,247,.4)';this.style.background='rgba(79,142,247,.08)'"
             onmouseout="this.style.borderColor='rgba(255,255,255,.1)';this.style.background='rgba(255,255,255,.05)'">
        <input type="radio" name="reportReason" value="<?php echo htmlspecialchars($r['label']); ?>"
               style="accent-color:#4f8ef7;width:16px;height:16px;flex-shrink:0;">
        <span style="font-size:13px;color:#f0eff5;font-family:'DM Sans',sans-serif;">
          <?php echo htmlspecialchars($r['label']); ?>
        </span>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Error message -->
    <div id="reportError"
         style="display:none;background:rgba(255,79,106,.12);border:1px solid rgba(255,79,106,.3);
                color:#ff7a8a;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px;">
    </div>

    <!-- Submit -->
    <button onclick="submitReport()"
            style="width:100%;padding:12px;background:linear-gradient(135deg,#ff4f6a,#c0392b);
                   border:none;border-radius:10px;color:#fff;font-family:'Sora',sans-serif;
                   font-size:14px;font-weight:700;cursor:pointer;transition:opacity .2s;"
            onmouseover="this.style.opacity='.85'"
            onmouseout="this.style.opacity='1'">
      Submit Report
    </button>

    <button onclick="closeReportModal()"
            style="width:100%;padding:10px;margin-top:8px;background:none;
                   border:1px solid rgba(255,255,255,.12);border-radius:10px;
                   color:#9b9ab0;font-family:'DM Sans',sans-serif;font-size:13px;
                   cursor:pointer;transition:all .2s;"
            onmouseover="this.style.background='rgba(255,255,255,.06)';this.style.color='#f0eff5'"
            onmouseout="this.style.background='none';this.style.color='#9b9ab0'">
      Cancel
    </button>
  </div>
</div>

<script>
// Report modal 
let _reportType = null;
let _reportId   = null;

function openReportModal(type, id) {
  _reportType = type;
  _reportId   = id;
  // Reset state
  document.querySelectorAll('input[name="reportReason"]')
    .forEach(r => r.checked = false);
  document.getElementById('reportError').style.display = 'none';
  // Update header label
  document.querySelector('#reportModal h3').textContent =
    '🚩 Report ' + (type === 'user' ? 'User' : 'Post');
  // Show
  const modal = document.getElementById('reportModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeReportModal() {
  document.getElementById('reportModal').style.display = 'none';
  document.body.style.overflow = '';
  _reportType = null;
  _reportId   = null;
}

function submitReport() {
  const selected = document.querySelector('input[name="reportReason"]:checked');
  const errEl    = document.getElementById('reportError');

  if (!selected) {
    errEl.textContent    = 'Please select a reason before submitting.';
    errEl.style.display  = 'block';
    return;
  }

  errEl.style.display = 'none';

  const body = new URLSearchParams({
    type:   _reportType,
    id:     _reportId,
    reason: selected.value
  });

  fetch('report.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    body.toString()
  })
  .then(r => r.json())
  .then(data => {
    closeReportModal();
    // Small toast
    showToast(data.message || 'Report submitted.');
  })
  .catch(() => {
    errEl.textContent   = 'Network error. Please try again.';
    errEl.style.display = 'block';
  });
}

// Close on backdrop
document.getElementById('reportModal').addEventListener('click', function(e) {
  if (e.target === this) closeReportModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' &&
      document.getElementById('reportModal').style.display !== 'none') {
    closeReportModal();
  }
});

// Toast notification
function showToast(msg) {
  // Remove existing toast
  const existing = document.getElementById('reportToast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'reportToast';
  toast.textContent = msg;
  toast.style.cssText = `
    position:fixed;bottom:28px;left:50%;transform:translateX(-50%);
    background:#1e1c35;border:1px solid rgba(255,255,255,.15);
    color:#f0eff5;padding:12px 24px;border-radius:10px;
    font-family:'DM Sans',sans-serif;font-size:14px;
    box-shadow:0 4px 20px rgba(0,0,0,.5);z-index:4000;
    animation:fadeInUp .25s ease;
  `;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .3s';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
function reportPost(id) { openReportModal('post', id); }
function reportUser(id) { openReportModal('user', id); }
</script>