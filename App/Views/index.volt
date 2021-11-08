
<div class="ui top attached tabular menu">
  <a class="active item" data-tab="first"> {{ t._('module_queue_callback_general') }} </a>
  <a class="item" data-tab="second"> {{ t._('module_queue_callback_queues') }} </a>
</div>

<form class="ui large grey segment form" id="module-queue-call-back-form">
    <div class="ui bottom attached active tab segment fluid" data-tab="first">
        {{ form.render('id') }}
        <div class="ten wide field disability">
            <label >{{ t._('module_queue_call_backNumberFieldLabel') }}</label>
            {{ form.render('peer_number') }}
        </div>

        <div class="ten wide field disability">
            <label>{{ t._('module_queue_call_backBillsecFieldLabel') }}</label>
            {{ form.render('call_billsec') }}
        </div>
        <div class="ten wide field disability">
            <label>{{ t._('module_queue_call_backDelay') }}</label>
            {{ form.render('delay') }}
        </div>

        <div class="ten wide field disability">
            <label>{{ t._('module_queue_call_backCountCallsFieldLabel') }}</label>
            {{ form.render('count_calls') }}
        </div>

        <div class="ten wide field disability">
            <label>{{ t._('module_queue_call_back_delta_no_answered_calls') }}</label>
            {{ form.render('delta_no_answered_calls') }}
        </div>
        <div class="ten wide field disability">
            <label>{{ t._('module_queue_call_back_alertForUser') }}</label>
            {{ form.render('alert_for_user') }}
        </div>
        <div class="ten wide field disability">
            <label>{{ t._('module_queue_annonce_order_ok') }}</label>
            {{ form.render('annonce_order_ok') }}
        </div>
    </div>
    <div class="ui bottom attached tab segment fluid" data-tab="second">
        <div class="ui grid">
            <div class="ui row">
                <div class="ui five wide column">
                    {{ link_to("#", '<i class="add user icon"></i>  '~t._('module_queueCallback_AddNewRecord'), "class": "ui blue button", "id":"add-new-row", "id-table":"ModuleQueueList-table") }}
                </div>
            </div>
        </div>
        <br>
        <table id="ModuleQueueList-table" class="ui small very compact single line table"></table>
    </div>

    {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
</form>


<select id="queues-list" style="display: none;">
    {% for record in queues %}
        <option value="{{ record.id }}">{{ record.name }}</option>
    {% endfor %}
</select>

<div id="template-select" style="display: none;">
    <div class="ui dropdown select-group" data-value="PARAM">
        <div class="text">PARAM</div>
        <i class="dropdown icon"></i>
    </div>
</div>