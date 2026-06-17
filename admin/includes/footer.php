        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-wrapper -->

<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const sidebarClose = document.getElementById('sidebarClose');

if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        document.body.classList.toggle('sidebar-open');
    });
}
if (sidebarClose) {
    sidebarClose.addEventListener('click', () => {
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    });
}

// Dropdown do usuário
const userMenu = document.getElementById('userMenu');
const userDropdown = document.getElementById('userDropdown');
if (userMenu && userDropdown) {
    userMenu.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('show');
    });
    document.addEventListener('click', () => {
        userDropdown.classList.remove('show');
    });
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 5000);
});
</script>
</body>
</html>
