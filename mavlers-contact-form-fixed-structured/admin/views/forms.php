                            <td class="column-actions">
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=edit&form_id=' . $form->id); ?>">
                                            <?php _e('Edit', 'mavlers-contact-form'); ?>
                                        </a> |
                                    </span>
                                    <span class="settings">
                                        <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=settings&form_id=' . $form->id); ?>">
                                            <?php _e('Settings', 'mavlers-contact-form'); ?>
                                        </a> |
                                    </span>
                                    <span class="trash">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=mavlers-forms&action=delete&form_id=' . $form->id), 'delete_form_' . $form->id); ?>" 
                                           class="submitdelete" 
                                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this form?', 'mavlers-contact-form'); ?>');">
                                            <?php _e('Delete', 'mavlers-contact-form'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td> 