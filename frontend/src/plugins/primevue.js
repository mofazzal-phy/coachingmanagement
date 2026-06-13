import PrimeVue from 'primevue/config'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import InputSwitch from 'primevue/inputswitch'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import Message from 'primevue/message'
import Divider from 'primevue/divider'
import SelectButton from 'primevue/selectbutton'
import Chip from 'primevue/chip'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import Card from 'primevue/card'
import Toast from 'primevue/toast'
import ToastService from 'primevue/toastservice'
import ConfirmDialog from 'primevue/confirmdialog'
import ConfirmationService from 'primevue/confirmationservice'
import Menu from 'primevue/menu'
import Sidebar from 'primevue/sidebar'
import PanelMenu from 'primevue/panelmenu'
import Menubar from 'primevue/menubar'
import TieredMenu from 'primevue/tieredmenu'
import FileUpload from 'primevue/fileupload'
import Calendar from 'primevue/calendar'
import MultiSelect from 'primevue/multiselect'
import Listbox from 'primevue/listbox'
import RadioButton from 'primevue/radiobutton'
import Checkbox from 'primevue/checkbox'
import Password from 'primevue/password'
import Skeleton from 'primevue/skeleton'
import InlineMessage from 'primevue/inlinemessage'
import ScrollPanel from 'primevue/scrollpanel'
import Toolbar from 'primevue/toolbar'
import SplitButton from 'primevue/splitbutton'
import SpeedDial from 'primevue/speeddial'
import Accordion from 'primevue/accordion'
import AccordionTab from 'primevue/accordiontab'
import Fieldset from 'primevue/fieldset'
import Panel from 'primevue/panel'
import Steps from 'primevue/steps'
import Image from 'primevue/image'
import Galleria from 'primevue/galleria'
import Carousel from 'primevue/carousel'
import Knob from 'primevue/knob'
import Rating from 'primevue/rating'
import ColorPicker from 'primevue/colorpicker'
import InputMask from 'primevue/inputmask'
import InputOtp from 'primevue/inputotp'
import TriStateCheckbox from 'primevue/tristatecheckbox'
import ToggleButton from 'primevue/togglebutton'
import AutoComplete from 'primevue/autocomplete'
import CascadeSelect from 'primevue/cascadeselect'
import TreeSelect from 'primevue/treeselect'
import Tree from 'primevue/tree'
import TreeTable from 'primevue/treetable'
import OrganizationChart from 'primevue/organizationchart'
import DataView from 'primevue/dataview'
import OrderList from 'primevue/orderlist'
import PickList from 'primevue/picklist'
import Timeline from 'primevue/timeline'
import VirtualScroller from 'primevue/virtualscroller'
import DeferredContent from 'primevue/deferredcontent'
import BlockUI from 'primevue/blockui'
import Dock from 'primevue/dock'
import Terminal from 'primevue/terminal'
import ContextMenu from 'primevue/contextmenu'
import MegaMenu from 'primevue/megamenu'
import Breadcrumb from 'primevue/breadcrumb'
import Inplace from 'primevue/inplace'
import OverlayPanel from 'primevue/overlaypanel'
import ConfirmPopup from 'primevue/confirmpopup'
import DynamicDialog from 'primevue/dynamicdialog'
import ScrollTop from 'primevue/scrolltop'
import Tooltip from 'primevue/tooltip'
import Ripple from 'primevue/ripple'
import StyleClass from 'primevue/styleclass'
import BadgeDirective from 'primevue/badgedirective'
import FocusTrap from 'primevue/focustrap'
import AnimateOnScroll from 'primevue/animateonscroll'
import InputIcon from 'primevue/inputicon'
import IconField from 'primevue/iconfield'
import FloatLabel from 'primevue/floatlabel'
import Slider from 'primevue/slider'
import Splitter from 'primevue/splitter'
import SplitterPanel from 'primevue/splitterpanel'
import Stepper from 'primevue/stepper'
import StepperPanel from 'primevue/stepperpanel'
import TabMenu from 'primevue/tabmenu'
import ButtonGroup from 'primevue/buttongroup'
import AvatarGroup from 'primevue/avatargroup'
import Row from 'primevue/row'
import ColumnGroup from 'primevue/columngroup'
import ProgressBar from 'primevue/progressbar'
import MeterGroup from 'primevue/metergroup'

// PrimeVue core styles (theme loaded dynamically via utils/theme.js)
import 'primevue/resources/primevue.min.css'
import 'primeicons/primeicons.css'

export default {
  install(app) {
    app.use(PrimeVue, { ripple: true })
    app.use(ToastService)
    app.use(ConfirmationService)

    // Directives
    app.directive('tooltip', Tooltip)
    app.directive('ripple', Ripple)
    app.directive('styleclass', StyleClass)
    app.directive('badge', BadgeDirective)
    app.directive('focustrap', FocusTrap)
    app.directive('animateonscroll', AnimateOnScroll)

    // Register PrimeVue components globally
    app.component('Button', Button)
    app.component('DataTable', DataTable)
    app.component('Column', Column)
    app.component('Dialog', Dialog)
    app.component('Dropdown', Dropdown)
    app.component('InputNumber', InputNumber)
    app.component('InputText', InputText)
    app.component('InputSwitch', InputSwitch)
    app.component('Textarea', Textarea)
    app.component('Tag', Tag)
    app.component('TabView', TabView)
    app.component('TabPanel', TabPanel)
    app.component('Paginator', Paginator)
    app.component('ProgressSpinner', ProgressSpinner)
    app.component('Message', Message)
    app.component('Divider', Divider)
    app.component('SelectButton', SelectButton)
    app.component('Chip', Chip)
    app.component('Badge', Badge)
    app.component('Avatar', Avatar)
    app.component('Card', Card)
    app.component('Toast', Toast)
    app.component('ConfirmDialog', ConfirmDialog)
    app.component('Menu', Menu)
    app.component('Sidebar', Sidebar)
    app.component('PanelMenu', PanelMenu)
    app.component('Menubar', Menubar)
    app.component('TieredMenu', TieredMenu)
    app.component('FileUpload', FileUpload)
    app.component('Calendar', Calendar)
    app.component('MultiSelect', MultiSelect)
    app.component('Listbox', Listbox)
    app.component('RadioButton', RadioButton)
    app.component('Checkbox', Checkbox)
    app.component('Password', Password)
    app.component('Skeleton', Skeleton)
    app.component('InlineMessage', InlineMessage)
    app.component('ScrollPanel', ScrollPanel)
    app.component('Toolbar', Toolbar)
    app.component('SplitButton', SplitButton)
    app.component('SpeedDial', SpeedDial)
    app.component('Accordion', Accordion)
    app.component('AccordionTab', AccordionTab)
    app.component('Fieldset', Fieldset)
    app.component('Panel', Panel)
    app.component('Steps', Steps)
    app.component('Image', Image)
    app.component('Galleria', Galleria)
    app.component('Carousel', Carousel)
    app.component('Knob', Knob)
    app.component('Rating', Rating)
    app.component('ColorPicker', ColorPicker)
    app.component('InputMask', InputMask)
    app.component('InputOtp', InputOtp)
    app.component('TriStateCheckbox', TriStateCheckbox)
    app.component('ToggleButton', ToggleButton)
    app.component('AutoComplete', AutoComplete)
    app.component('CascadeSelect', CascadeSelect)
    app.component('TreeSelect', TreeSelect)
    app.component('Tree', Tree)
    app.component('TreeTable', TreeTable)
    app.component('OrganizationChart', OrganizationChart)
    app.component('DataView', DataView)
    app.component('OrderList', OrderList)
    app.component('PickList', PickList)
    app.component('Timeline', Timeline)
    app.component('VirtualScroller', VirtualScroller)
    app.component('DeferredContent', DeferredContent)
    app.component('BlockUI', BlockUI)
    app.component('Dock', Dock)
    app.component('Terminal', Terminal)
    app.component('ContextMenu', ContextMenu)
    app.component('MegaMenu', MegaMenu)
    app.component('Breadcrumb', Breadcrumb)
    app.component('Inplace', Inplace)
    app.component('OverlayPanel', OverlayPanel)
    app.component('ConfirmPopup', ConfirmPopup)
    app.component('DynamicDialog', DynamicDialog)
    app.component('ScrollTop', ScrollTop)
    app.component('InputIcon', InputIcon)
    app.component('IconField', IconField)
    app.component('FloatLabel', FloatLabel)
    app.component('Slider', Slider)
    app.component('Splitter', Splitter)
    app.component('SplitterPanel', SplitterPanel)
    app.component('Stepper', Stepper)
    app.component('StepperPanel', StepperPanel)
    app.component('TabMenu', TabMenu)
    app.component('ButtonGroup', ButtonGroup)
    app.component('AvatarGroup', AvatarGroup)
    app.component('Row', Row)
    app.component('ColumnGroup', ColumnGroup)
    app.component('ProgressBar', ProgressBar)
    app.component('MeterGroup', MeterGroup)
  }
}
