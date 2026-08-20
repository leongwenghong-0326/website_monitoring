    </main>
</div>
<?php
$jsVer = (string) (@filemtime(ROOT_PATH . '/assets/js/admin.js') ?: time());
$liveVer = (string) (@filemtime(ROOT_PATH . '/assets/js/live-time.js') ?: time());
?>
<script src="<?php echo e(url('assets/js/live-time.js?v=' . $liveVer)); ?>"></script>
<script src="<?php echo e(url('assets/js/admin.js?v=' . $jsVer)); ?>"></script>
</body>
</html>
