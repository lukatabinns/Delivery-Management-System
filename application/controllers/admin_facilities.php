<?php
class Admin_facilities extends CI_Controller {

    /**
    * name of the folder responsible for the views 
    * which are manipulated by this controller
    * @constant string
    */
    const VIEW_FOLDER = 'admin/facilities';
 
    /**
    * Responsable for auto load the model
    * @return void
    */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('facilities_model');

        if(!$this->session->userdata('is_logged_in')){
            redirect('admin/login');
        }
    }
 
    /**
    * Load the main view with all the current model model's data.
    * @return void
    */
    public function index()
    {

        //all the posts sent by the view
        $search_string = $this->input->post('search_string');        
        $order = $this->input->post('order'); 
        $order_type = $this->input->post('order_type'); 

        //pagination settings
        $config['per_page'] = 5;

        $config['base_url'] = base_url().'admin/facilities';
        $config['use_page_numbers'] = TRUE;
        $config['num_links'] = 20;
        $config['full_tag_open'] = '<ul>';
        $config['full_tag_close'] = '</ul>';
        $config['num_tag_open'] = '<li>';
        $config['num_tag_close'] = '</li>';
        $config['cur_tag_open'] = '<li class="active"><a>';
        $config['cur_tag_close'] = '</a></li>';

        //limit end
        $page = $this->uri->segment(3);

        //math to get the initial record to be select in the database
        $limit_end = ($page * $config['per_page']) - $config['per_page'];
        if ($limit_end < 0){
            $limit_end = 0;
        } 

        //if order type was changed
        if($order_type){
            $filter_session_data['order_type'] = $order_type;
        }
        else{
            //we have something stored in the session? 
            if($this->session->userdata('order_type')){
                $order_type = $this->session->userdata('order_type');    
            }else{
                //if we have nothing inside session, so it's the default "Asc"
                $order_type = 'Asc';    
            }
        }
        //make the data type var avaible to our view
        $data['order_type_selected'] = $order_type;        


        //we must avoid a page reload with the previous session data
        //if any filter post was sent, then it's the first time we load the content
        //in this case we clean the session filter data
        //if any filter post was sent but we are in some page, we must load the session data

        //filtered && || paginated
        if($search_string !== false && $order !== false || $this->uri->segment(3) == true){ 
           
            /*
            The comments here are the same for line 79 until 99

            if post is not null, we store it in session data array
            if is null, we use the session data already stored
            we save order into the the var to load the view with the param already selected       
            */
            if($search_string){
                $filter_session_data['search_string_selected'] = $search_string;
            }else{
                $search_string = $this->session->userdata('search_string_selected');
            }
            $data['search_string_selected'] = $search_string;

            if($order){
                $filter_session_data['order'] = $order;
            }
            else{
                $order = $this->session->userdata('order');
            }
            $data['order'] = $order;

            //save session data into the session
            if(isset($filter_session_data)){
              $this->session->set_userdata($filter_session_data);    
            }
            
            //fetch sql data into arrays
            $data['count_deliveries']= $this->facilities_model->count_facilities($search_string, $order);
            $config['total_rows'] = $data['count_deliveries'];

            //fetch sql data into arrays
            if($search_string){
                if($order){
                    $data['facilities'] = $this->facilities_model->get_facilities($search_string, $order, $order_type, $config['per_page'],$limit_end);
                }else{
                    $data['facilities'] = $this->facilities_model->get_facilities($search_string, '', $order_type, $config['per_page'],$limit_end);
                }
            }else{
                if($order){
                    $data['facilities'] = $this->facilities_model->get_facilities('', $order, $order_type, $config['per_page'],$limit_end);
                }else{
                    $data['facilities'] = $this->facilities_model->get_facilities('', '', $order_type, $config['per_page'],$limit_end);
                }
            }

        }else{

            //clean filter data inside section
            $filter_session_data['facility_selected'] = null;
            $filter_session_data['search_string_selected'] = null;
            $filter_session_data['order'] = null;
            $filter_session_data['order_type'] = null;
            $this->session->set_userdata($filter_session_data);

            //pre selected options
            $data['search_string_selected'] = '';
            $data['order'] = 'id';

            //fetch sql data into arrays
            $data['count_deliveries']= $this->facilities_model->count_facilities();
            $data['facilities'] = $this->facilities_model->get_facilities('', '', $order_type, $config['per_page'],$limit_end);
            $config['total_rows'] = $data['count_deliveries'];

        }//!isset($search_string) && !isset($order)
         
        //initializate the panination helper 
        $this->pagination->initialize($config);   

        //load the view
        $data['main_content'] = 'admin/facilities/list';
        $this->load->view('includes/template', $data);  

    }//index
    public function check_postcode($input)
    {
        if (preg_match('/^([0-1][0-9]|[2][0-3]):([0-5][0-9])$/', $input))
        {
            $this->form_validation->set_message('facility_postcode','Sorry but not a valid postcode');
            return true; // it matched, return true or false if you want opposite
        }
        else
        {
            return false;
        }
    }
    public function add()
    {
        //if save button was clicked, get the data sent via post
        if ($this->input->server('REQUEST_METHOD') === 'POST')
        {

            //form validation
            $this->form_validation->set_rules('facility_name', 'Name', 'required');
            $this->form_validation->set_rules('facility_address1', 'House Number', 'required');
            $this->form_validation->set_rules('facility_address2', 'Street', 'required');
            $this->form_validation->set_rules('facility_locality', 'Town/City', 'required');
            $this->form_validation->set_rules('facility_postcode', 'Postcode', 'required|callback_check_postcode');

            $this->form_validation->set_rules('facility_max_capacity', 'Max Capacity', 'required|numeric');
            $this->form_validation->set_error_delimiters('<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a><strong>', '</strong></div>');
            

            //if the form has passed through the validation
            if ($this->form_validation->run())
            {
                $data_to_store = array(
                    'facility_name' => $this->input->post('facility_name'),
                );
                //if the insert has returned true then we show the flash message
                if($this->facilities_model->store_facility($data_to_store)){
                    $data['flash_message'] = TRUE; 
                }else{
                    $data['flash_message'] = FALSE; 
                }

            }

        }
        //load the view
        $data['main_content'] = 'admin/facilities/add';
        $this->load->view('includes/template', $data);  
    }       

    /**
    * Update item by his id
    * @return void
    */
    public function update()
    {
        //product id 
        $id = $this->uri->segment(4);
  
        //if save button was clicked, get the data sent via post
        if ($this->input->server('REQUEST_METHOD') === 'POST')
        {
            //form validation
            $this->form_validation->set_rules('facility_name', 'Name', 'required');
            $this->form_validation->set_error_delimiters('<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a><strong>', '</strong></div>');
            //if the form has passed through the validation
            if ($this->form_validation->run())
            {
    
                $data_to_store = array(
                    'facility_name' => $this->input->post('facility_name'),
                );
                //if the insert has returned true then we show the flash message
                if($this->facilities_model->update_facility($id, $data_to_store) == TRUE){
                    $this->session->set_flashdata('flash_message', 'updated');
                }else{
                    $this->session->set_flashdata('flash_message', 'not_updated');
                }
                // redirect to main facilities view
                redirect('admin/facilities');

            }//validation run

        }

        //if we are updating, and the data did not pass trough the validation
        //the code below wel reload the current data

        //product data 
        $data['facility'] = $this->facilities_model->get_facility_by_id($id);
        //load the view
        $data['main_content'] = 'admin/facilities/edit';
        $this->load->view('includes/template', $data);

    }//update

    /**
    * Delete product by his id
    * @return void
    */
    public function delete()
    {
        //product id 
        $id = $this->uri->segment(4);
        $this->manufacturers_model->delete_facility($id);
        redirect('admin/facilities');
    }//edit

}