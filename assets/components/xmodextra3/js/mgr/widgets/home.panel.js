xModExtra3.panel.Home = function (config) {
    config = config || {};
    Ext.apply(config, {
        baseCls: 'modx-formpanel',
        layout: 'anchor',
        /*
         stateful: true,
         stateId: 'xmodextra3-panel-home',
         stateEvents: ['tabchange'],
         getState:function() {return {activeTab:this.items.indexOf(this.getActiveTab())};},
         */
        hideMode: 'offsets',
        items: [{
            html: '<h2>' + _('xmodextra3') + '</h2>',
            cls: '',
            style: {margin: '15px 0'}
        }, {
            xtype: 'modx-tabs',
            defaults: {border: false, autoHeight: true},
            border: true,
            hideMode: 'offsets',
            items: [{
                title: _('xmodextra3_items'),
                layout: 'anchor',
                items: [{
                    html: _('xmodextra3_intro_msg'),
                    cls: 'panel-desc',
                }, {
                    xtype: 'xmodextra3-grid-items',
                    cls: 'main-wrapper',
                }]
            }]
        }]
    });
    xModExtra3.panel.Home.superclass.constructor.call(this, config);
};
Ext.extend(xModExtra3.panel.Home, MODx.Panel);
Ext.reg('xmodextra3-panel-home', xModExtra3.panel.Home);
