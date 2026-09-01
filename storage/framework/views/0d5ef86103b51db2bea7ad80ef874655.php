<?php $__env->startSection('title', 'ERS | Edit Transaction'); ?>
<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('pages.client_transaction.newTransaction', ['isEditMode' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\client_transaction\transactionEdit.blade.php ENDPATH**/ ?>