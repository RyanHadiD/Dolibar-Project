<?php

require_once __DIR__.'/lib/common.php';

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$requestedDoctype = GETPOST('doctype', 'aZ09');
if (!$requestedDoctype) {
	$requestedDoctype = 'invoice';
}
if ($action === 'save') {
	pfPost();
	try {
		$data = array(
			'name' => pfPostValue('name'),
			'doctype' => pfPostValue('doctype', 'invoice'),
			'template_html' => pfPostValue('template_html'),
			'template_css' => pfPostValue('template_css'),
			'page_format' => pfPostValue('page_format', 'A4'),
			'custom_width' => pfPostValue('custom_width'),
			'custom_height' => pfPostValue('custom_height'),
			'orientation' => pfPostValue('orientation', 'P'),
			'margin_top' => pfPostValue('margin_top', '12'),
			'margin_right' => pfPostValue('margin_right', '12'),
			'margin_bottom' => pfPostValue('margin_bottom', '12'),
			'margin_left' => pfPostValue('margin_left', '12'),
			'active' => isset($_POST['active']),
			'is_default' => isset($_POST['is_default']),
		);
		$id = $printFormatRepository->save($id, $data, $user->id);
		setEventMessages('Print format berhasil disimpan.', null, 'mesgs');
		header('Location: '.pfUrl('card.php?id='.$id));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

if ($id) {
	try {
		$format = $printFormatRepository->get($id);
	} catch (Throwable $e) {
		accessforbidden($e->getMessage());
	}
} else {
	$format = (object) PrintFormat::defaultData($requestedDoctype);
}

$doctypeLabels = PrintFormatDoctypeRegistry::labels();
$previewObjects = $id ? pfObjectChoices($format->doctype) : array();

pfHeader($id ? 'Edit Print Format' : 'Buat Print Format', true);
print '<p><a href="'.pfEscape(pfUrl('index.php')).'">← Kembali ke daftar</a></p>';
print '<div class="pf-help">Gunakan <code>{{ variable }}</code>, <code>{% if variable %}...{% endif %}</code>, dan <code>{% for line in lines %}...{% endfor %}</code>. Field yang tidak dimiliki suatu doctype akan menghasilkan teks kosong. Semua nilai di-escape otomatis; kode PHP dan JavaScript tidak diizinkan.</div>';
print '<form method="post" class="pf-editor"><input type="hidden" name="token" value="'.pfEscape(newToken()).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.(int) $id.'">';
print '<div class="pf-settings"><label>Nama<input class="flat minwidth300" name="name" maxlength="128" required value="'.pfEscape($format->name).'"></label><label>DocType<select class="flat minwidth200" name="doctype" id="doctype">';
foreach ($doctypeLabels as $key => $label) {
	print '<option value="'.pfEscape($key).'"'.($format->doctype === $key ? ' selected' : '').'>'.pfEscape($label).'</option>';
}
print '</select></label><label>Ukuran<select class="flat" name="page_format" id="page_format"><option value="A4"'.($format->page_format === 'A4' ? ' selected' : '').'>A4</option><option value="LETTER"'.($format->page_format === 'LETTER' ? ' selected' : '').'>Letter</option><option value="CUSTOM"'.($format->page_format === 'CUSTOM' ? ' selected' : '').'>Custom</option></select></label>';
print '<div class="pf-custom-size" id="pf_custom_size"><label>Lebar (mm)<input class="flat width75" type="number" min="20" max="2000" step="0.1" name="custom_width" id="custom_width" value="'.pfEscape($format->custom_width ?? '').'"></label><span class="pf-size-separator">×</span><label>Tinggi (mm)<input class="flat width75" type="number" min="20" max="2000" step="0.1" name="custom_height" id="custom_height" value="'.pfEscape($format->custom_height ?? '').'"></label></div>';
print '<label>Orientasi<select class="flat" name="orientation"><option value="P"'.($format->orientation === 'P' ? ' selected' : '').'>Portrait</option><option value="L"'.($format->orientation === 'L' ? ' selected' : '').'>Landscape</option></select></label>';
foreach (array('margin_top' => 'Atas', 'margin_right' => 'Kanan', 'margin_bottom' => 'Bawah', 'margin_left' => 'Kiri') as $field => $label) {
	print '<label>Margin '.$label.' (mm)<input class="flat width50" type="number" min="0" max="50" name="'.$field.'" value="'.(int) $format->$field.'"></label>';
}
print '<label class="pf-check"><input type="checkbox" name="active" value="1"'.(!empty($format->active) ? ' checked' : '').'> Aktif</label><label class="pf-check"><input type="checkbox" name="is_default" value="1"'.(!empty($format->is_default) ? ' checked' : '').'> Jadikan default</label></div>';
print '<div class="pf-workspace"><div class="pf-code">';
print '<div class="pf-code-heading"><label for="template_html_editor">HTML</label><button class="button pf-format-code" type="button" data-editor="template_html_editor">Rapikan HTML <span class="pf-shortcut">Ctrl+Shift+F</span></button></div><div id="template_html_editor" class="pf-ace pf-ace-html" aria-label="Editor template HTML"></div><textarea id="template_html" class="pf-editor-source" name="template_html" spellcheck="false">'.pfEscape($format->template_html).'</textarea>';
print '<div class="pf-code-heading"><label for="template_css_editor">CSS</label><button class="button pf-format-code" type="button" data-editor="template_css_editor">Rapikan CSS <span class="pf-shortcut">Ctrl+Shift+F</span></button></div><div id="template_css_editor" class="pf-ace pf-ace-css" aria-label="Editor template CSS"></div><textarea id="template_css" class="pf-editor-source" name="template_css" spellcheck="false">'.pfEscape($format->template_css).'</textarea>';
print '</div><aside class="pf-fields"><input id="pf-field-search" class="flat" placeholder="Cari field...">';
foreach (pfFields($format->doctype) as $group => $fields) {
	print '<section><b>'.pfEscape($group).'</b>';
	foreach ($fields as $field) {
		print '<button type="button" class="pf-field" data-field="{{ '.pfEscape($field).' }}">{{ '.pfEscape($field).' }}</button>';
	}
	print '</section>';
}
print '</aside></div><div class="tabsAction"><button class="button button-save" type="submit">Simpan</button></div></form>';

if ($id) {
	print '<div class="pf-previewbar"><label>Dokumen preview <select class="flat minwidth300" id="preview_object"><option value="">— Pilih '.pfEscape($doctypeLabels[$format->doctype]).' —</option>';
	foreach ($previewObjects as $objectId => $objectLabel) {
		print '<option value="'.(int) $objectId.'">'.pfEscape($objectLabel !== '' ? $objectLabel : '#'.$objectId).'</option>';
	}
	print '</select></label><button class="button" type="button" id="preview_button">Muat Preview PDF</button></div><iframe id="pf-preview" class="pf-preview" title="Print preview PDF"></iframe>';
}
print '<script nonce="'.getNonce().'">jQuery(function(){
	var editors={};
	var beautify=window.ace.require("ace/ext/beautify");
	function formatEditor(editor){beautify.beautify(editor.session);editor.focus();}
	function normalizeNewlines(value){return value.replace(/\\\\r\\\\n/g,"\n").replace(/\\\\r\n/g,"\n").replace(/\\\\n/g,"\n").replace(/\\\\r/g,"\n").replace(/\r\n?/g,"\n");}
	function makeEditor(sourceId,editorId,mode){
		var source=document.getElementById(sourceId),editor=window.ace.edit(editorId),value=normalizeNewlines(source.value);
		editor.setValue(value,-1);
		editor.session.setMode("ace/mode/"+mode);
		editor.session.setUseSoftTabs(true);
		editor.session.setTabSize(2);
		editor.session.setUseWrapMode(true);
		editor.setTheme("ace/theme/chrome");
		editor.setOptions({fontSize:"13px",showPrintMargin:false,displayIndentGuides:true,enableBasicAutocompletion:false});
		editor.commands.addCommand({name:"formatCode",bindKey:{win:"Ctrl-Shift-F",mac:"Command-Shift-F"},exec:formatEditor});
		editors[editorId]=editor;
		source.closest(".pf-code").classList.add("pf-code-enhanced");
		if(value.length>120&&value.indexOf("\n")===-1){window.setTimeout(function(){formatEditor(editor);editor.clearSelection();editor.moveCursorTo(0,0);},0);}
		return editor;
	}
	var htmlEditor=makeEditor("template_html","template_html_editor","twig");
	makeEditor("template_css","template_css_editor","css");
	jQuery(".pf-editor").on("submit",function(){document.getElementById("template_html").value=editors.template_html_editor.getValue();document.getElementById("template_css").value=editors.template_css_editor.getValue();});
	jQuery(".pf-format-code").on("click",function(){formatEditor(editors[jQuery(this).data("editor")]);});
	jQuery(".pf-field").on("click",function(){htmlEditor.insert(jQuery(this).data("field"));htmlEditor.focus();});
	jQuery("#pf-field-search").on("input",function(){var q=this.value.toLowerCase();jQuery(".pf-field").each(function(){jQuery(this).toggle(jQuery(this).text().toLowerCase().indexOf(q)!==-1);});});
	jQuery("#doctype").on("change",function(){if('.(int) $id.'===0){window.location='.json_encode(pfUrl('card.php?doctype=')).'+encodeURIComponent(this.value);}});
	function toggleCustomSize(){var custom=jQuery("#page_format").val()==="CUSTOM";jQuery("#pf_custom_size").toggle(custom);jQuery("#custom_width,#custom_height").prop("required",custom).prop("disabled",!custom);}
	jQuery("#page_format").on("change",toggleCustomSize);toggleCustomSize();
	jQuery("#preview_button").on("click",function(){var objectId=jQuery("#preview_object").val();if(!objectId){return;}jQuery("#pf-preview").attr("src",'.json_encode(pfUrl('preview.php?format_id='.$id.'&object_id=')).'+objectId+"&_="+Date.now());});
});</script>';
pfEnd();
