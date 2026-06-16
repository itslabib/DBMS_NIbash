    </div><!-- /p-8 -->
</main>

<script>
    if(typeof lucide !== 'undefined') lucide.createIcons();
    const clockEl = document.getElementById('sys-clock');
    if(clockEl) setInterval(() => { clockEl.textContent = new Date().toLocaleTimeString('en-US',{hour12:false}); }, 1000);
</script>
</body>
</html>
