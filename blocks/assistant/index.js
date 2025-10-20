(function(wp){
  const { registerBlockType } = wp.blocks;
  registerBlockType('luxai/assistant', {
    edit: function(){ return wp.element.createElement('div', {}, 'Lux AI Assistant will render on the front-end.'); },
    save: function(){ return null; }
  });
})(window.wp);