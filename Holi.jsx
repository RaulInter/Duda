
const [initialDate, setInitialDate] = useState('');
const [finalDate, setFinalDate] = useState('');
const [extraInitialDate, setExtraInitialDate] = useState('');
const [extraFinalDate, setExtraFinalDate] = useState('');
const [daysDifference, setDaysDifference] = useState(0);

<>
    <div className='col-xs-12 col-md-6 col-xl-6 pt-1'>
        <Input
            type='date'
            label='Día no laboral'
            className='form-control'
            name='extraInitialDate'
            id='extraInitialDate'
            value={extraInitialDate}
            {...register('extraInitialDate')}
            onChange={(e) => setExtraInitialDate(e.target.value)}
        />
        {errors.extraInitialDate && (
            <p>
                <i className='bx bx-sm bx-info-circle mt-1' />
                {errors.extraInitialDate.message}
            </p>
        )}
    </div>

    <div className='col-xs-12 col-md-6 col-xl-6 pt-1'>
        <Input
            type='date'
            label='Día no laboral 2'
            className='form-control'
            name='extraFinalDate'
            id='extraFinalDate'
            value={extraFinalDate}
            {...register('extraFinalDate')}
            onChange={(e) => setExtraFinalDate(e.target.value)}
        />
        {errors.extraFinalDate && (
            <p>
                <i className='bx bx-sm bx-info-circle mt-1' />
                {errors.extraFinalDate.message}
            </p>
        )}
    </div>
</>