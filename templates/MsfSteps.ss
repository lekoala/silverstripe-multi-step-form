<div class="msf-steps">
<% loop AllSteps %>
<a <% if Link %>href="$Link"<% end_if %> title="$Title" data-toggle="tooltip" class="$Class">$Number</a>
<% end_loop %>
</div>
