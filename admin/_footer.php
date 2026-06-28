</div><!-- /page-body -->
</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toast helper
    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast-msg ' + type;
        t.innerHTML = `<i class="fa-solid ${type==='success'?'fa-check-circle':'fa-circle-exclamation'}"></i> ${msg}`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 4000);
    }
    // URL toast
    const urlParams = new URLSearchParams(location.search);
    if (urlParams.get('success')) showToast(decodeURIComponent(urlParams.get('success')));
    if (urlParams.get('error')) showToast(decodeURIComponent(urlParams.get('error')), 'error');
</script>
</body>

</html>