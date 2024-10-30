const getData = (key) => {
  const data = window.wc.wcSettings.getSetting(key);
  return (key, defaultValue = null) => {
      if (!data.hasOwnProperty(key)) {
          data[key] = defaultValue;
      }
      return data[key];
  };
}

const data = getData('kanoo_data');

let _iconEl =  React.createElement('img', { src: data('icon'), alt: data('title') }, null)
let _labelChilds = [
  React.createElement('span', { className: 'kano-payment-label' }, data('title')),
  React.createElement('div', { className: 'kano-payment-icon' }, [_iconEl])
]
let labelEl = React.createElement('div', { key: 'kano-payment-label', id: 'kano-payment-checkout-label', className: 'kano-payment-label-container'}, [_labelChilds]);
let contentEl = React.createElement('p', { key: 'kano-payment' }, data('description'));
let editEl = React.createElement('p', { key: 'kanoo-payment-edit' }, '');  

let options = {
  label: labelEl,
  ariaLabel: data('title'),
  name: 'kanoo',
  content: contentEl,
  edit: editEl,
  canMakePayment: function() {return true;},
  paymentMethodId: 'kanoo',
  supports: {
    features: data('supports'),
  }
};
window.wc.wcBlocksRegistry.registerPaymentMethod( options );