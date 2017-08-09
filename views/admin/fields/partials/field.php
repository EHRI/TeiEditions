<tr>
    <td class="element">

        <input
            name="mapping[<?php echo $field->slug; ?>][id]"
            value="<?php echo $field->id; ?>"
            type="hidden"
        />
    </td>

    <td>
        <input
            name="mapping[<?php echo $field->slug; ?>][path]"
            value="<?php echo htmlspecialchars($field->path); ?>"
            type="text"
        />
    </td>
</tr>
