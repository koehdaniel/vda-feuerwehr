</main>
<script>
    // Aktuellen Menüpunkt markieren
    document.querySelectorAll('.tabs a').forEach(function (a) {
        if (location.pathname.endsWith('/' + a.getAttribute('href'))) a.classList.add('active');
    });
</script>
</body>
</html>
