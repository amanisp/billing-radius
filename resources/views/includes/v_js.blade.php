<script src="{{ asset('assets/static/js/components/dark.js') }}"></script>
<script src="{{ asset('assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>

<script src="{{ asset('assets/compiled/js/app.js') }}"></script>

<!-- Need: Apexcharts -->
<script src="{{ asset('assets/extensions/apexcharts/apexcharts.min.js') }}"></script>
<script src="{{ asset('assets/static/js/pages/dashboard.js') }}"></script>

<script src="{{ asset('extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
<script src="{{ asset('static/js/pages/form-element-select.js') }}"></script>

<script src="{{ asset('extensions/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>





<script>
    // Theme
    const htmlEl = document.documentElement;
    const themeOptions = document.querySelectorAll(".theme-option");

    themeOptions.forEach(option => {
        option.addEventListener("click", function(e) {
            e.preventDefault();
            let theme = this.getAttribute("data-theme");

            if (theme === "dark") {
                htmlEl.setAttribute("data-bs-theme", "dark");
            } else if (theme === "light") {
                htmlEl.setAttribute("data-bs-theme", "light");
            } else {
                htmlEl.removeAttribute("data-bs-theme"); // system default
            }

            localStorage.setItem("theme", theme);
        });
    });

    // load preferensi saat reload
    window.addEventListener("DOMContentLoaded", () => {
        let savedTheme = localStorage.getItem("theme") || "system";
        if (savedTheme === "dark") {
            htmlEl.setAttribute("data-bs-theme", "dark");
        } else if (savedTheme === "light") {
            htmlEl.setAttribute("data-bs-theme", "light");
        }
    });

    // Footer
    async function getPublicIP() {
        try {
            let response = await fetch("https://ipwho.is/");
            let data = await response.json();
            let ip = data.ip + " " + data.connection.isp;

            // Dapatkan informasi OS & Browser
            let userAgent = navigator.userAgent;
            let platform = navigator.platform;
            let browser = getBrowserName(userAgent);

            document.getElementById("public-ip").innerHTML = `Public IP: ${ip}`;
            document.getElementById("device-info").innerHTML = `OS: ${platform} | Browser: ${browser}`;
        } catch (error) {
            console.error("Gagal mendapatkan IP:", error);
            document.getElementById("public-ip").innerHTML = "Gagal mendapatkan IP";
        }
    }

    function getBrowserName(userAgent) {
        if (userAgent.match(/chrome|chromium|crios/i)) {
            return "Google Chrome";
        } else if (userAgent.match(/firefox|fxios/i)) {
            return "Mozilla Firefox";
        } else if (userAgent.match(/safari/i)) {
            return "Apple Safari";
        } else if (userAgent.match(/opr\//i)) {
            return "Opera";
        } else if (userAgent.match(/edg/i)) {
            return "Microsoft Edge";
        } else {
            return "Unknown Browser";
        }
    }

    getPublicIP();
</script>


<script>
    $("#table1").DataTable({
        responsive: true,
    });
    $(document).ready(function() {
        $('#customerSelect').select2({
            dropdownParent: $('#formCreateModal')
            theme: 'bootstrap-5',
            allowClear: true,
        });
    });
</script>
</body>

</html>
