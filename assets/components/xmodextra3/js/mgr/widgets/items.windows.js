xModExtra3.window.CreateItem = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'xmodextra3-item-window-create';
    }
    Ext.applyIf(config, {
        title: _('xmodextra3_item_create'),
        width: 550,
        autoHeight: true,
        url: xModExtra3.config.connector_url,
        action: 'xModExtra3\\Processors\\Item\\Create',
        fields: this.getFields(config),
        keys: [{
            key: Ext.EventObject.ENTER, shift: true, fn: function () {
                this.submit()
            }, scope: this
        }]
    });
    xModExtra3.window.CreateItem.superclass.constructor.call(this, config);
};
Ext.extend(xModExtra3.window.CreateItem, MODx.Window, {

    getFields: function (config) {
        return [{
            xtype: 'textfield',
            fieldLabel: _('xmodextra3_item_name'),
            name: 'name',
            id: config.id + '-name',
            anchor: '99%',
            allowBlank: false,
        }, {
            xtype: 'textarea',
            fieldLabel: _('xmodextra3_item_description'),
            name: 'description',
            id: config.id + '-description',
            height: 150,
            anchor: '99%'
        }, {
            xtype: 'xcheckbox',
            boxLabel: _('xmodextra3_item_active'),
            name: 'active',
            id: config.id + '-active',
            checked: true,
        }];
    },

    loadDropZones: function () {
    }

});
Ext.reg('xmodextra3-item-window-create', xModExtra3.window.CreateItem);


xModExtra3.window.UpdateItem = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'xmodextra3-item-window-update';
    }
    Ext.applyIf(config, {
        title: _('xmodextra3_item_update'),
        width: 550,
        autoHeight: true,
        url: xModExtra3.config.connector_url,
        action: 'xModExtra3\\Processors\\Item\\Update',
        fields: this.getFields(config),
        keys: [{
            key: Ext.EventObject.ENTER, shift: true, fn: function () {
                this.submit()
            }, scope: this
        }]
    });
    xModExtra3.window.UpdateItem.superclass.constructor.call(this, config);
};
Ext.extend(xModExtra3.window.UpdateItem, MODx.Window, {

    getFields: function (config) {
        return [{
            xtype: 'hidden',
            name: 'id',
            id: config.id + '-id',
        }, {
            xtype: 'textfield',
            fieldLabel: _('xmodextra3_item_name'),
            name: 'name',
            id: config.id + '-name',
            anchor: '99%',
            allowBlank: false,
        }, {
            xtype: 'textarea',
            fieldLabel: _('xmodextra3_item_description'),
            name: 'description',
            id: config.id + '-description',
            anchor: '99%',
            height: 150,
        }, {
            xtype: 'xcheckbox',
            boxLabel: _('xmodextra3_item_active'),
            name: 'active',
            id: config.id + '-active',
        }];
    },

    loadDropZones: function () {
    }

});
Ext.reg('xmodextra3-item-window-update', xModExtra3.window.UpdateItem);